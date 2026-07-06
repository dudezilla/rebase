#!/usr/bin/env python3
"""turn_example.py — a no-op EXAMPLE crank patch (the shape mint_crank.py expects).

A crank patch is ONE self-contained python script that makes ONE change. Copy this, put your
change in main(), then mint it:

    python3 checkouts/current/tools/mint_crank.py --patch your_patch.py --name your_name -m "..."

Contract (note-for-claude + the fixes/*.py ratchet-link pattern):
  * python only, no shell;
  * make exactly ONE change;
  * on success, upsert an entry into fixes/index.json (the ratchet ledger);
  * on ANY exception, auto-file a Variant-A bug report and re-raise.

This example changes nothing but its own ledger entry — safe to mint as a smoke test.
"""
import json
import os
import sys
import time
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))          # fixes/  or fixes/examples/
FIXES = HERE if os.path.basename(HERE) == "fixes" else os.path.dirname(HERE)
SOURCE = os.path.dirname(FIXES)
INDEX = os.path.join(FIXES, "index.json")


def _root(start=HERE):
    d = os.path.abspath(start)
    while True:
        if os.path.isfile(os.path.join(d, "registry.json")):
            return d
        p = os.path.dirname(d)
        if p == d:
            return os.path.dirname(os.path.dirname(SOURCE))
        d = p


def bug_report(exc, tb):
    path = os.path.join(_root(), "file-system-repair", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank patch: turn_example"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record(purpose):
    entry = {"fix": os.path.basename(__file__), "target": "(none — example)", "purpose": purpose,
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    # <-- your ONE change goes here. This example makes none beyond its ledger entry.
    record("example crank patch — no-op smoke turn")
    print(json.dumps({"ok": True, "changed": "fixes/index.json (example entry)"}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        tb = traceback.format_exc()
        bug_report(exc, tb)
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
