# The tracker safety layer — signals, the dead-man's switch, and the signals MCP daemon

This is the write-up for the operational safety layer around the project tracker: **why it exists, how
the pieces fit, what an MCP and a daemon are here, and the fail-safe philosophy that drives it.**

---

## 1. Why this exists

The ticket ledger (`tracker/tickets.jsonl`) is going to be **edited whether we like it or not** — by hand,
by an agent, by accident. When a ticket is added (on purpose or not), vanishes, or can't be found, **we
need to be notified**, and that notification has to be **looked up from a database**, not scraped from a
file. So we need:

- a **DB error channel** (a `signals` table) that any process writes to and anyone can query;
- **reconciliation** that turns ledger drift into signals automatically;
- a **fail-safe watchdog** whose *default condition is error* — the healthy component must actively hold
  the error back, so a dead or hung component is **detected, never silently assumed fine.**

---

## 2. Architecture at a glance

```
 tracker/tickets.jsonl        append-only ledger — the committed SOURCE OF TRUTH (on GitHub)
        │  (sync, "no matter what")
        ▼
 tracker/store.py  ──►  ~/.MCP/project.db   (ONE PERSISTENT INSTALL db — $PROJECT_DB overrides)
        │                 ├── tickets    projection of the ledger (rebuildable)
        │                 ├── signals    the DB ERROR CHANNEL  ◄── errors looked up here
        │                 ├── heartbeat  the DEAD-MAN's SWITCH (default state = error)
        │                 └── memories   the gate's tool-use log (durable, NOT rebuildable) ◄── gate writes here
        ▼
 ~/.MCP/signals-mcp/server.py   the DAEMON (watchdog thread) + the error-channel TOOLS
        │  registry.json → servers.signals
        ▼
 the gate  ──►  signals__due · signals__ack · signals__sync · signals__check · signals__lookup · signals__status
```

The ledger stays the source of truth (committed, diffable, append-only). `project.db` is **one persistent,
install-level db** — by default `~/.MCP/project.db` (the install root, beside the gate and registry;
override with `$PROJECT_DB`). It is deliberately **not** a copy in the disposable working tree, because the
`memories` table (the gate's tool-use log) is durable operational history with **no ledger to rebuild it
from**. Only `tickets` is a projection one `sync` can rebuild; `signals`/`heartbeat` are transient
operational state; `memories` is the durable data that makes the db worth persisting across a repo re-clone.

---

## 3. `store.py` — the DB + the safety functions

Three tables:

| table | purpose |
|---|---|
| `tickets(id, type, description, status, created)` | projection of the ledger (rebuildable via `sync`) |
| `signals(id, ts, level, source, code, message, ref, status)` | the **error channel** — every process writes here; errors are read with a query |
| `heartbeat(component, last_seen, ttl, state)` | the **dead-man's switch**, one row per component |
| `memories(id, ts, session, tool, intent, note)` | the **gate's tool-use log** — one row per gated call, tagged with the Claude Code **session** id; durable (no ledger to rebuild it), the reason the db is a persistent install |

Key functions:

- **`sync_from_ledger()`** — migrate/refresh the db from the ledger **"no matter what"**: the ledger read
  is wrapped so **any** exception becomes a `READER_EXCEPTION` error signal (and, deliberately, *no beat*
  — so the dead-man's switch will also fire). It then **reconciles**: a ticket in the ledger but not the db
  → `TICKET_ADDED`; in the db but not the ledger → `TICKET_VANISHED`. A clean run **beats** `ticket_reader`.
- **`raise_signal(level, source, code, message, ref)`** — append a signal. `level` ∈ `error|warn|info`.
- **`signals(level=None, status="open")`** / **`ack_signal(id, status)`** — read / resolve the channel.
- **`lookup_ticket(id)`** — read a ticket *from the db*; a miss itself raises `TICKET_NOT_FOUND`.
- **`beat(component, ttl)`** / **`check_deadman()`** — the dead-man's switch (below).

---

## 4. The dead-man's switch — the safety philosophy

> The default condition is **error**. The functional component holds it back.

Each watched component has a `heartbeat` row with a `ttl`. The healthy component calls `beat()` on every
success, pushing `last_seen` forward. `check_deadman()` walks the heartbeats and, for any whose
`now - last_seen > ttl`, flips its state to `error` and raises a **`DEADMAN_EXPIRED`** signal.

This inverts the usual "raise an error when something breaks" into **"assume broken unless proven
alive."** A component that crashes, hangs, or is killed simply *stops beating* — and silence is read as
failure, not success. There is no way to fail *quietly*: doing nothing trips the switch.

The functional component here is the **daemon** (§6): every clean `sync` beats `ticket_reader`. If the
daemon dies, `ticket_reader` goes stale and the next `check_deadman()` (on daemon restart, or a manual
`signals__check`) fires the switch.

---

## 5. What an MCP is (in this project)

An **MCP** is a small stdio server that exposes a set of **tools**. In this stack they live under
`~/.MCP/<name>-mcp/server.py`, are listed in `~/.MCP/registry.json` under `servers`, and are aggregated by
the **gate** (a coupler) so their tools surface to the agent as **`<name>__<tool>`** (e.g.
`signals__due`). A server is minimal:

```python
import mcplib
TOOLS = [{"name": "...", "description": "...", "inputSchema": {...}, "handler": fn}, ...]
def list_tools(): ...
def call_tool(name, args): ...
mcplib.Server("signals", "1.0.0", list_tools, call_tool).serve()   # the stdio loop
```

Registration (in `registry.json`):

```json
"signals": { "command": "python3", "args": ["signals-mcp/server.py"],
             "env": { "TRACKER_DIR": "../big-jazz-main/packages/congruencey/tracker" } }
```

`TRACKER_DIR` tells the server where the tracker's `store.py` lives; `SIGNALS_TICK` (default 30s) sets the
daemon cadence.

---

## 6. What a DAEMON is here (the watchdog)

A plain MCP is **request/response** — it only acts when a tool is called. The safety layer needs
something running *between* calls, so the signals MCP starts a **background daemon thread** before it
begins serving:

```python
threading.Thread(target=_daemon, daemon=True).start()
mcplib.Server("signals", "1.0.0", list_tools, call_tool).serve()
```

The daemon loop, every `SIGNALS_TICK` seconds:

1. `sync_from_ledger()` — pull the ledger into the db (reconciliation signals fall out automatically);
2. `check_deadman()` — fire error signals for any component gone silent;
3. wraps everything in a last-resort `try/except` → a `DAEMON_EXCEPTION` error signal, because **the
   watchdog itself must never die silently.**

This is the "MCP that sends a signal if it catches any exception whatsoever at the component that reads
`tickets.jsonl`" **and** the thing that "drops a dead-man's switch." The daemon *is* the functional
component that holds the switch back; kill the MCP and the switch arms itself.

**Standalone equivalent** (no MCP): `python3 tracker/store.py watch [seconds]` runs the same loop in the
foreground — handy for testing or a cron/systemd unit.

---

## 7. The tools (`signals__*`)

| tool | what it does |
|---|---|
| `signals__due [level]` | list OPEN signals from the db error channel (errors/warnings + reconciliation + dead-man fires) |
| `signals__ack {id, status?}` | resolve a signal (append-only status; signals are never deleted) |
| `signals__sync` | force a ledger→db sync now (also beats the switch); returns counts |
| `signals__check` | run the dead-man's switch check now |
| `signals__lookup {id}` | look up a ticket from the db; a miss raises `TICKET_NOT_FOUND` |
| `signals__status` | open signal + error counts, the daemon tick, recent errors |

---

## 8. Signal codes

| code | level | meaning |
|---|---|---|
| `TICKET_ADDED` | info | a ticket appeared in the ledger (added on purpose or by accident) |
| `TICKET_VANISHED` | warn | a ticket in the db is gone from the ledger (edited away) |
| `TICKET_NOT_FOUND` | error | a lookup referenced a ticket that isn't in the db |
| `READER_EXCEPTION` | error | the ledger reader threw — sync caught it (and did **not** beat) |
| `DEADMAN_EXPIRED` | error | a component stopped beating; the switch fired |
| `DAEMON_EXCEPTION` | error | the watchdog loop itself threw |

---

## 9. Guarantees & failure modes

- **Corrupt/edited ledger** → `READER_EXCEPTION` (+ no beat → `DEADMAN_EXPIRED` shortly after). You are
  told twice, from two independent mechanisms.
- **Daemon dies** → `ticket_reader` goes stale → `DEADMAN_EXPIRED` on next check. Silence ≠ health.
- **DB briefly locked** → connections use a 5s busy timeout; signal writes never nest inside another open
  write transaction (that was a real bug we fixed).
- **`project.db` lost** → `tickets` rebuild from the ledger on one `sync`, and `signals`/`heartbeat` are
  transient by design — but **`memories` are gone for good** (there is no ledger behind them). That is
  exactly why the db is a **persistent install file at `~/.MCP/project.db`**, not a copy in the disposable
  working tree: a repo re-clone or a `git clean` must not take the tool-use history with it.

---

## 10. Deploying / keeping in sync

The running server lives at `~/.MCP/signals-mcp/server.py` (so it can import the shared `~/.MCP/mcplib`);
the **source of record** is versioned in the repo at `tracker/signals-mcp/server.py`. To deploy or
update: copy the repo copy over the `~/.MCP` copy and restart the gate. (A future ticket: make this a
one-command install so the two never drift — see the tracker's own `build`/`spec` tickets.)
