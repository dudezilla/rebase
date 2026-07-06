#!/usr/bin/env python3
"""tournament_dedup.py — crank: remove the nested-duplicate tooling/tournament/ wrapper.

ONE change: tooling/tournament/ is EXACTLY a wrapper nesting byte-identical copies of
tooling/tournament-package/ (248 files) and tooling/tournament-lineage/ (219 files) — verified by
identical subtree hashes, no code references. Remove tooling/tournament/, keeping the canonical
top-level tournament-package/ + tournament-lineage/. Fix the tooling/README.md row.
Self-records to fixes/index.json; Variant-A bug report on exception.
"""
import json
import os
import shutil
import sys
import time
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
FIXES = HERE if os.path.basename(HERE) == "fixes" else os.path.dirname(HERE)
SOURCE = os.path.dirname(FIXES)
INDEX = os.path.join(FIXES, "index.json")


def _root(d=HERE):
    while d != os.path.dirname(d):
        if os.path.isfile(os.path.join(d, "registry.json")):
            return d
        d = os.path.dirname(d)
    return os.path.dirname(os.path.dirname(SOURCE))


ROOT = _root()
OLD_ROW = "| `tournament/`, `tournament-lineage/`, `tournament-package/` |"
NEW_ROW = "| `tournament-lineage/`, `tournament-package/` |"


def bug_report(exc, tb):
    reg = os.path.join(ROOT, "registry.json")
    rel = "logs/bug_reports.jsonl"
    if os.path.isfile(reg):
        try:
            rel = json.load(open(reg)).get("bug_reports", rel)
        except Exception:
            pass
    path = os.path.join(ROOT, rel)
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: tournament_dedup"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "tooling/tournament/ (removed), tooling/README.md",
             "purpose": "dedup: remove nested-duplicate tooling/tournament/ (keeps top-level tournament-package/ + -lineage/)",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    tdir = os.path.join(ROOT, "tooling", "tournament")
    if not os.path.isdir(tdir):
        raise RuntimeError("tooling/tournament/ not found (already removed?)")
    # safety: only proceed if it holds nothing but the two nested dirs
    kids = sorted(os.listdir(tdir))
    if kids != ["tournament-lineage", "tournament-package"]:
        raise RuntimeError("tooling/tournament/ unexpected contents: %s" % kids)
    shutil.rmtree(tdir)

    rp = os.path.join(ROOT, "tooling", "README.md")
    if os.path.isfile(rp):
        txt = open(rp).read()
        if OLD_ROW not in txt:
            raise RuntimeError("tooling/README.md tournament row anchor not found")
        open(rp, "w").write(txt.replace(OLD_ROW, NEW_ROW))

    record()
    print(json.dumps({"ok": True, "removed": "tooling/tournament/", "kept": ["tooling/tournament-package/", "tooling/tournament-lineage/"]}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
