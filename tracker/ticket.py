#!/usr/bin/env python3
"""ticket.py — the congruency project ticket tracker (append-only ledger; REST + CLI; human + machine).

Governance (learned the hard way):
  * IDs are AUTO-STAMPED — callers (agents included) never supply them.
  * Tickets are NEVER deleted or combined — you only ever APPEND a status change.
  * The ledger (tracker/tickets.jsonl) is append-only; the current state is a projection of it.

Fields: id (auto), type (enum), description, status (tracked via appended status events).
Types: bug, build, design, spec, bet, test, documentation, rfc-investigation.

CLI:
    ticket.py new <type> "<description>"        # mints an id, status=open
    ticket.py status <id> <status> ["note"]     # append a status change
    ticket.py list [--json]                     # current state (one row per ticket)
    ticket.py show <id>                          # one ticket + full status history
    ticket.py serve [host:port]                  # REST + human view (default 127.0.0.1:8737)

REST:
    GET  /tickets                 -> JSON list          (machine)
    GET  /tickets/<id>            -> JSON one + history
    GET  /                        -> HTML table         (human)
    POST /tickets   {type, description}   -> 201 {id}
    POST /tickets/<id>/status  {status, note}  -> 200 {ok}
"""
import http.server
import json
import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
LEDGER = os.path.join(HERE, "tickets.jsonl")
TYPES = ["bug", "build", "design", "spec", "bet", "test", "documentation", "rfc-investigation"]
STATUSES = ["open", "active", "blocked", "review", "done", "dropped"]   # suggested; any string accepted


def _now():
    return time.strftime("%Y-%m-%dT%H:%M:%S")


def _events():
    if not os.path.isfile(LEDGER):
        return []
    out = []
    for ln in open(LEDGER):
        ln = ln.strip()
        if ln:
            try:
                out.append(json.loads(ln))
            except ValueError:
                pass
    return out


def _append(ev):
    os.makedirs(os.path.dirname(LEDGER), exist_ok=True)
    with open(LEDGER, "a") as fh:
        fh.write(json.dumps(ev) + "\n")


def _next_id(events=None):
    events = _events() if events is None else events
    ids = [e["id"] for e in events if e.get("kind") == "new"]
    return (max(ids) + 1) if ids else 1


def create(type_, description):
    """Mint a new ticket. id is auto-stamped; status starts 'open'. Returns the id."""
    if type_ not in TYPES:
        raise ValueError("type must be one of %s" % TYPES)
    if not description or not str(description).strip():
        raise ValueError("description required")
    tid = _next_id()
    _append({"kind": "new", "id": tid, "type": type_, "description": str(description).strip(), "ts": _now()})
    _append({"kind": "status", "id": tid, "status": "open", "note": "filed", "ts": _now()})
    return tid


def set_status(tid, status, note=""):
    """Append a status change. Never deletes; never combines."""
    tid = int(tid)
    if not any(e.get("kind") == "new" and e.get("id") == tid for e in _events()):
        raise ValueError("no ticket #%s" % tid)
    if not status or not str(status).strip():
        raise ValueError("status required")
    _append({"kind": "status", "id": tid, "status": str(status).strip(), "note": str(note), "ts": _now()})
    return True


def state():
    """Project the append-only ledger into current ticket state (sorted by id)."""
    tickets = {}
    for e in _events():
        if e.get("kind") == "new":
            tickets[e["id"]] = {"id": e["id"], "type": e["type"], "description": e["description"],
                                "created": e["ts"], "status": None, "history": []}
    for e in _events():
        if e.get("kind") == "status" and e.get("id") in tickets:
            tickets[e["id"]]["status"] = e["status"]
            tickets[e["id"]]["history"].append({"status": e["status"], "note": e.get("note", ""), "ts": e["ts"]})
    return [tickets[k] for k in sorted(tickets)]


def get(tid):
    for t in state():
        if t["id"] == int(tid):
            return t
    return None


# --------------------------------------------------------------------------- #
# REST + human view                                                           #
# --------------------------------------------------------------------------- #
def _esc(s):
    return str(s).replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")


def _html(tickets):
    rows = "".join(
        "<tr><td>#%d</td><td>%s</td><td><b>%s</b></td><td>%s</td></tr>"
        % (t["id"], _esc(t["type"]), _esc(t["status"]), _esc(t["description"]))
        for t in tickets)
    return ("<!doctype html><html><head><meta charset='utf-8'><title>congruency tickets</title>"
            "<style>body{font-family:Georgia,serif;max-width:860px;margin:2rem auto;color:#222;background:#f7f4ee}"
            "table{border-collapse:collapse;width:100%%}td,th{border:1px solid #ccc;padding:6px 8px;text-align:left;"
            "vertical-align:top}th{background:#eae5d8}h1{font-weight:normal}</style></head><body>"
            "<h1>Congruency &mdash; project tickets</h1>"
            "<p>%d tickets. Types: %s. Machine view: <a href='/tickets'>/tickets</a> (JSON).</p>"
            "<table><tr><th>id</th><th>type</th><th>status</th><th>description</th></tr>%s</table>"
            "</body></html>") % (len(tickets), ", ".join(TYPES), rows)


class Handler(http.server.BaseHTTPRequestHandler):
    def _send(self, code, body, ctype="application/json"):
        b = body.encode() if isinstance(body, str) else body
        self.send_response(code)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(b)))
        self.end_headers()
        self.wfile.write(b)

    def do_GET(self):
        path = self.path.split("?")[0]
        if path in ("/", "/index.html"):
            self._send(200, _html(state()), "text/html; charset=utf-8")
        elif path == "/tickets":
            self._send(200, json.dumps(state(), indent=2))
        elif path.startswith("/tickets/"):
            try:
                tid = int(path.split("/")[2])
            except (ValueError, IndexError):
                return self._send(400, json.dumps({"error": "bad id"}))
            t = get(tid)
            self._send(200 if t else 404, json.dumps(t or {"error": "not found"}))
        else:
            self._send(404, json.dumps({"error": "not found"}))

    def do_POST(self):
        n = int(self.headers.get("Content-Length", 0) or 0)
        raw = self.rfile.read(n).decode() if n else ""
        try:
            data = json.loads(raw) if raw else {}
        except ValueError:
            data = {}
        path = self.path.split("?")[0]
        try:
            if path == "/tickets":
                tid = create(data.get("type"), data.get("description"))
                self._send(201, json.dumps({"id": tid}))
            elif path.startswith("/tickets/") and path.endswith("/status"):
                tid = int(path.split("/")[2])
                set_status(tid, data.get("status"), data.get("note", ""))
                self._send(200, json.dumps({"ok": True, "id": tid}))
            else:
                self._send(404, json.dumps({"error": "not found"}))
        except ValueError as e:
            self._send(400, json.dumps({"error": str(e)}))

    def log_message(self, *a):
        pass


def serve(host="127.0.0.1", port=8737):
    http.server.HTTPServer((host, port), Handler).serve_forever()


def main():
    a = sys.argv[1:]
    if not a:
        print(__doc__)
        return 0
    cmd = a[0]
    try:
        if cmd == "new":
            print(create(a[1], a[2]))
        elif cmd == "status":
            set_status(a[1], a[2], a[3] if len(a) > 3 else "")
            print("ok")
        elif cmd == "list":
            st = state()
            if "--json" in a:
                print(json.dumps(st, indent=2))
            else:
                for t in st:
                    print("#%-3d [%-16s] %-8s %s" % (t["id"], t["type"], t["status"], t["description"][:64]))
        elif cmd == "show":
            print(json.dumps(get(a[1]), indent=2))
        elif cmd == "serve":
            hp = (a[1] if len(a) > 1 else "127.0.0.1:8737").split(":")
            host = hp[0] or "127.0.0.1"
            port = int(hp[1]) if len(hp) > 1 else 8737
            print("serving tickets on http://%s:%d/  (Ctrl-C to stop)" % (host, port))
            serve(host, port)
        else:
            print("unknown command: %s" % cmd, file=sys.stderr)
            return 2
    except (IndexError, ValueError) as e:
        print("error: %s" % e, file=sys.stderr)
        return 2
    return 0


if __name__ == "__main__":
    sys.exit(main())
