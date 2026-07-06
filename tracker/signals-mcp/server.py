#!/usr/bin/env python3
"""signals-mcp — the tracker's operational safety DAEMON + DB error channel.

Two jobs in one MCP:
  1. A background WATCHDOG thread. On every tick it SYNCs the ticket ledger (tracker/tickets.jsonl)
     into the db (project.db) and runs the DEAD-MAN's switch check — catching ANY exception into an
     ERROR signal instead of failing silently. A clean sync BEATS the dead-man's switch; if this daemon
     dies, the reader stops beating and the switch fires on the next check (default condition is error).
  2. The DB ERROR CHANNEL as tools, so agents and humans read errors FROM the db, not the file:
     signals__due, signals__ack, signals__sync, signals__check, signals__lookup, signals__status.

Owned by the coupler (registry.json → servers.signals). Reuses the tracker's store.py (path from
$TRACKER_DIR). Zero extra deps; shares ~/.MCP/mcplib.
"""
import importlib.util
import os
import sys
import threading
import time

HERE = os.path.dirname(os.path.abspath(__file__))
MCP_DIR = os.path.dirname(HERE)                       # ~/.MCP
sys.path.insert(0, MCP_DIR)                            # mcplib, registry.json
import mcplib  # noqa: E402

TRACKER = os.environ.get("TRACKER_DIR") or os.path.normpath(
    os.path.join(MCP_DIR, "..", "big-jazz-main", "packages", "congruencey", "tracker"))
TICK = int(os.environ.get("SIGNALS_TICK", "30"))


def _load_store():
    spec = importlib.util.spec_from_file_location("store", os.path.join(TRACKER, "store.py"))
    m = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(m)
    return m


store = _load_store()


def _daemon():
    """The watchdog loop — the functional component that HOLDS BACK the default-error dead-man's switch."""
    while True:
        try:
            store.sync_from_ledger()      # any reader exception is caught INSIDE and becomes a signal + no beat
            store.check_deadman()          # fire error signals for components that went silent
        except Exception as exc:           # last-resort catch: the daemon itself must never die silently
            try:
                store.raise_signal("error", "signals-mcp", "DAEMON_EXCEPTION",
                                   "%s: %s" % (type(exc).__name__, exc))
            except Exception:
                pass
        time.sleep(TICK)


def _fmt(sigs):
    if not sigs:
        return "_no open signals._"
    return "\n".join("- [%s] **%s** `%s` — %s%s" % (
        s["ts"], s["level"], s["code"], s["message"], (" (ref=%s)" % s["ref"]) if s["ref"] else "")
        for s in sigs)


def tool_due(args):
    args = args or {}
    lvl = args.get("level")
    sigs = store.signals(level=lvl, status="open")
    return mcplib.text_result("## signals — %d open%s\n\n%s" % (
        len(sigs), " (level=%s)" % lvl if lvl else "", _fmt(sigs)))


def tool_ack(args):
    args = args or {}
    if args.get("id") is None:
        return mcplib.text_result("provide `id`", True)
    st = args.get("status", "resolved")
    store.ack_signal(int(args["id"]), st)
    return mcplib.text_result("signal #%s → %s" % (args["id"], st))


def tool_sync(args):
    return mcplib.text_result("sync (ledger → db): %s" % store.sync_from_ledger())


def tool_check(args):
    stale = store.check_deadman()
    return mcplib.text_result("dead-man's switch — fired for: %s" % (stale or "none (all components beating)"))


def tool_lookup(args):
    args = args or {}
    if args.get("id") is None:
        return mcplib.text_result("provide `id`", True)
    t = store.lookup_ticket(int(args["id"]))
    return mcplib.text_result("ticket #%s: %s" % (
        args["id"], t if t else "**NOT FOUND** — a TICKET_NOT_FOUND error signal was raised"))


def tool_status(args):
    sigs = store.signals(status="open")
    errs = [s for s in sigs if s["level"] == "error"]
    return mcplib.text_result("## tracker safety status\nopen signals: **%d** (errors: **%d**) · daemon tick: %ds\n\n%s"
                              % (len(sigs), len(errs), TICK, _fmt(errs[:10])))


TOOLS = [
    {"name": "due",
     "description": "List OPEN signals from the db error channel — errors, warnings, and the reconciliation signals (TICKET_ADDED / TICKET_VANISHED / TICKET_NOT_FOUND) plus dead-man fires. Optional `level` (error|warn|info).",
     "inputSchema": {"type": "object", "properties": {"level": {"type": "string"}}, "additionalProperties": False},
     "handler": tool_due},
    {"name": "ack",
     "description": "Acknowledge/resolve an open signal by `id` (append-only status change; signals are never deleted). Optional `status` (default 'resolved').",
     "inputSchema": {"type": "object", "properties": {"id": {"type": "integer"}, "status": {"type": "string"}}, "required": ["id"], "additionalProperties": False},
     "handler": tool_ack},
    {"name": "sync",
     "description": "Force a ledger→db sync now (migrates tickets.jsonl into the db 'no matter what' and BEATS the dead-man's switch). Returns counts + reconciliation.",
     "inputSchema": {"type": "object", "properties": {}, "additionalProperties": False},
     "handler": tool_sync},
    {"name": "check",
     "description": "Run the dead-man's switch check now; fires an error signal for any component that stopped beating (default condition is error).",
     "inputSchema": {"type": "object", "properties": {}, "additionalProperties": False},
     "handler": tool_check},
    {"name": "lookup",
     "description": "Look up a ticket by `id` FROM the db; a miss itself raises a TICKET_NOT_FOUND error signal (you asked to be notified on not-found).",
     "inputSchema": {"type": "object", "properties": {"id": {"type": "integer"}}, "required": ["id"], "additionalProperties": False},
     "handler": tool_lookup},
    {"name": "status",
     "description": "Tracker safety status: open signal + error counts, the daemon tick, and the most recent errors.",
     "inputSchema": {"type": "object", "properties": {}, "additionalProperties": False},
     "handler": tool_status},
]
_BY_NAME = {t["name"]: t["handler"] for t in TOOLS}


def list_tools():
    return [{"name": t["name"], "description": t["description"], "inputSchema": t["inputSchema"]} for t in TOOLS]


def call_tool(name, args):
    handler = _BY_NAME.get(name)
    if not handler:
        return mcplib.text_result("Unknown tool: %s" % name, True)
    return handler(args or {})


if __name__ == "__main__":
    threading.Thread(target=_daemon, daemon=True).start()   # the watchdog daemon
    mcplib.Server("signals", "1.0.0", list_tools, call_tool).serve()
