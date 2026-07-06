#!/usr/bin/env python3
"""store.py — the project DB layer: tickets (projected from the ledger), signals, and a dead-man's switch.

The append-only ledger (tracker/tickets.jsonl) stays the committed source of truth; this DB
(tracker/project.db, git-ignored) is the queryable projection PLUS an operational safety layer:

  tickets(id, type, description, status, created)          -- projection of the ledger
  signals(id, ts, level, source, code, message, ref, status) -- the DB error channel; errors looked up here
  heartbeat(component, last_seen, ttl, state)              -- dead-man's switch, DEFAULT state = ERROR

Safety-oriented by design:
  * `sync_from_ledger()` pushes the ledger into the DB "no matter what" — every step is wrapped so ANY
    exception becomes an ERROR signal (never a silent crash), and it reconciles: a ticket ADDED to the
    ledger externally, one that VANISHED, a PARSE error, or a NOT-FOUND ref each raise a signal.
  * The dead-man's switch DEFAULTS to error. The functional reader must `beat()` to hold it back; if it
    stops beating (crash/hang), `check_deadman()` fires an error signal. Silence == failure.

CLI: store.py sync | signals [--level error] | beat <component> [ttl] | check | watch [seconds] | status
"""
import json
import os
import sqlite3
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
DB = os.path.join(HERE, "project.db")
LEDGER = os.path.join(HERE, "tickets.jsonl")

SCHEMA = """
CREATE TABLE IF NOT EXISTS tickets (
    id INTEGER PRIMARY KEY, type TEXT, description TEXT, status TEXT, created TEXT);
CREATE TABLE IF NOT EXISTS signals (
    id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT, level TEXT, source TEXT,
    code TEXT, message TEXT, ref TEXT, status TEXT DEFAULT 'open');
CREATE TABLE IF NOT EXISTS heartbeat (
    component TEXT PRIMARY KEY, last_seen TEXT, ttl INTEGER, state TEXT);
"""


def _now():
    return time.strftime("%Y-%m-%dT%H:%M:%S")


def _con():
    con = sqlite3.connect(DB, timeout=5)
    con.row_factory = sqlite3.Row
    con.executescript(SCHEMA)
    return con


# --------------------------------------------------------------------------- #
# signals — the DB error channel                                              #
# --------------------------------------------------------------------------- #
def raise_signal(level, source, code, message="", ref=""):
    """Record a signal (level: error|warn|info). Errors are looked up FROM the db via signals()."""
    con = _con()
    con.execute("INSERT INTO signals (ts, level, source, code, message, ref, status) VALUES (?,?,?,?,?,?, 'open')",
                (_now(), level, source, code, str(message)[:500], str(ref)))
    con.commit()
    con.close()


def signals(level=None, status="open"):
    con = _con()
    q = "SELECT * FROM signals WHERE 1=1"
    args = []
    if status:
        q += " AND status=?"; args.append(status)
    if level:
        q += " AND level=?"; args.append(level)
    rows = [dict(r) for r in con.execute(q + " ORDER BY id DESC", args)]
    con.close()
    return rows


def ack_signal(sig_id, status="resolved"):
    con = _con()
    con.execute("UPDATE signals SET status=? WHERE id=?", (status, int(sig_id)))
    con.commit()
    con.close()


# --------------------------------------------------------------------------- #
# dead-man's switch — DEFAULT state is ERROR; the reader must beat to hold it back
# --------------------------------------------------------------------------- #
def beat(component, ttl=60):
    """The functional component asserts health. Absence of a beat within ttl == error (fail-safe)."""
    con = _con()
    con.execute("INSERT INTO heartbeat (component, last_seen, ttl, state) VALUES (?,?,?, 'ok') "
                "ON CONFLICT(component) DO UPDATE SET last_seen=excluded.last_seen, ttl=excluded.ttl, state='ok'",
                (component, _now(), int(ttl)))
    con.commit()
    con.close()


def check_deadman():
    """Fire an error signal for any component whose heartbeat has expired (or was never seen)."""
    con = _con()
    stale = []
    for r in con.execute("SELECT * FROM heartbeat").fetchall():
        age = time.time() - time.mktime(time.strptime(r["last_seen"], "%Y-%m-%dT%H:%M:%S"))
        if age > r["ttl"] and r["state"] != "error":
            con.execute("UPDATE heartbeat SET state='error' WHERE component=?", (r["component"],))
            stale.append(r["component"])
    con.commit()
    con.close()
    for c in stale:
        raise_signal("error", "deadman", "DEADMAN_EXPIRED",
                     "component '%s' stopped asserting health (dead-man's switch fired)" % c, ref=c)
    return stale


# --------------------------------------------------------------------------- #
# sync — push the ledger into the db "no matter what" + reconcile -> signals   #
# --------------------------------------------------------------------------- #
def _ledger_state():
    """Project tracker/tickets.jsonl (reuse ticket.py if importable, else read directly)."""
    try:
        sys.path.insert(0, HERE)
        import ticket
        return ticket.state()
    except Exception:  # noqa: BLE001 — fall back to a direct read so sync never depends on the reader
        out = {}
        for ln in open(LEDGER):
            ln = ln.strip()
            if not ln:
                continue
            e = json.loads(ln)   # a parse error here is caught by sync_from_ledger and signalled
            if e.get("kind") == "new":
                out[e["id"]] = {"id": e["id"], "type": e["type"], "description": e["description"],
                                "created": e["ts"], "status": "open"}
            elif e.get("kind") == "status" and e.get("id") in out:
                out[e["id"]]["status"] = e["status"]
        return list(out.values())


def sync_from_ledger():
    """Migrate/refresh the db from the ledger. ANY exception -> ERROR signal (never a silent crash);
    reconciliation raises signals for tickets ADDED, VANISHED, or otherwise inconsistent."""
    try:
        ledger = {t["id"]: t for t in _ledger_state()}
    except Exception as exc:  # noqa: BLE001 — the MCP-style catch: any reader exception becomes a signal
        raise_signal("error", "ticket_reader", "READER_EXCEPTION",
                     "%s: %s" % (type(exc).__name__, exc), ref=LEDGER)
        return {"ok": False, "error": str(exc)}   # NB: no beat() -> the dead-man's switch will fire too

    con = _con()
    db_ids = {r["id"] for r in con.execute("SELECT id FROM tickets")}
    added = [tid for tid in ledger if tid not in db_ids]
    vanished = [tid for tid in db_ids if tid not in ledger]
    for tid, t in ledger.items():
        con.execute("INSERT INTO tickets (id, type, description, status, created) VALUES (?,?,?,?,?) "
                    "ON CONFLICT(id) DO UPDATE SET type=excluded.type, description=excluded.description, "
                    "status=excluded.status, created=excluded.created",
                    (tid, t["type"], t["description"], t.get("status"), t.get("created")))
    con.commit()
    con.close()
    # raise reconciliation signals AFTER the ticket-write txn is closed (avoid nested-connection locks)
    for tid in added:
        raise_signal("info", "sync", "TICKET_ADDED",
                     "ticket #%s appeared in the ledger (type=%s)" % (tid, ledger[tid]["type"]), ref=tid)
    for tid in vanished:
        raise_signal("warn", "sync", "TICKET_VANISHED",
                     "ticket #%s is in the db but no longer in the ledger (edited away?)" % tid, ref=tid)
    beat("ticket_reader")   # a clean read holds the dead-man's switch back
    return {"ok": True, "synced": len(ledger), "added": len(added), "vanished": len(vanished)}


def lookup_ticket(tid):
    """Look up a ticket FROM the db; a miss is itself a signal (you asked to be notified on not-found)."""
    con = _con()
    r = con.execute("SELECT * FROM tickets WHERE id=?", (int(tid),)).fetchone()
    con.close()
    if r is None:
        raise_signal("error", "lookup", "TICKET_NOT_FOUND", "ticket #%s not found in the db" % tid, ref=tid)
        return None
    return dict(r)


def main():
    a = sys.argv[1:]
    cmd = a[0] if a else "status"
    if cmd == "sync":
        print(json.dumps(sync_from_ledger()))
    elif cmd == "signals":
        lvl = a[a.index("--level") + 1] if "--level" in a else None
        for s in signals(level=lvl):
            print("  [%s] %-8s %-18s %s%s" % (s["ts"], s["level"], s["code"], s["message"],
                                              (" (ref=%s)" % s["ref"]) if s["ref"] else ""))
    elif cmd == "beat":
        beat(a[1], int(a[2]) if len(a) > 2 else 60); print("beat %s" % a[1])
    elif cmd == "check":
        stale = check_deadman(); print("dead-man fired for: %s" % (stale or "none"))
    elif cmd == "lookup":
        print(json.dumps(lookup_ticket(a[1])))
    elif cmd == "watch":
        every = int(a[1]) if len(a) > 1 else 15
        print("watching: sync + dead-man every %ds (Ctrl-C to stop)" % every)
        while True:
            sync_from_ledger(); check_deadman()
            time.sleep(every)
    elif cmd == "status":
        con = _con()
        print("tickets: %d  ·  open signals: %d (errors: %d)  ·  components: %s" % (
            con.execute("SELECT COUNT(*) FROM tickets").fetchone()[0],
            con.execute("SELECT COUNT(*) FROM signals WHERE status='open'").fetchone()[0],
            con.execute("SELECT COUNT(*) FROM signals WHERE status='open' AND level='error'").fetchone()[0],
            [dict(r)["component"] + ":" + dict(r)["state"] for r in con.execute("SELECT * FROM heartbeat")] or "none"))
        con.close()
    else:
        print("usage: store.py sync|signals|beat|check|lookup|watch|status", file=sys.stderr)
        return 2
    return 0


if __name__ == "__main__":
    sys.exit(main())
