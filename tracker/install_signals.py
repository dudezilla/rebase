#!/usr/bin/env python3
"""install_signals.py — #37: one-command sync of the repo signals-mcp server into ~/.MCP.

The SOURCE OF RECORD is the versioned tracker/signals-mcp/server.py; the RUNNING copy lives at
~/.MCP/signals-mcp/server.py so it can import the shared ~/.MCP/mcplib. Those two drifting was the
hazard noted in SIGNALS.md §10 — this copies the repo copy over the ~/.MCP copy so they can't.
Run it after editing the server, then restart the gate/coupler.

    python3 tracker/install_signals.py            # sync + verify identical
    python3 tracker/install_signals.py --check     # report drift only (exit 1 if drifted)

~/.MCP is OUTSIDE this repo, so only this script (the source-of-record server) is versioned here.
"""
import argparse, filecmp, os, shutil, sys

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, "signals-mcp", "server.py")
DST = os.path.expanduser(os.path.join("~", ".MCP", "signals-mcp", "server.py"))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true", help="report drift only; do not copy")
    a = ap.parse_args()
    if not os.path.isfile(SRC):
        print("source of record missing: %s" % SRC)
        return 2

    in_sync = os.path.isfile(DST) and filecmp.cmp(SRC, DST, shallow=False)
    if a.check:
        print("signals-mcp server: %s" % ("in sync ✓" if in_sync else "DRIFTED (run without --check to fix)"))
        return 0 if in_sync else 1

    os.makedirs(os.path.dirname(DST), exist_ok=True)
    shutil.copyfile(SRC, DST)
    ok = filecmp.cmp(SRC, DST, shallow=False)
    print("synced %s -> %s : %s" % (os.path.relpath(SRC, HERE), DST, "identical ✓" if ok else "MISMATCH!"))
    print("(restart the gate/coupler so the running daemon reloads it)")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
