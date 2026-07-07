#!/usr/bin/env python3
"""doc_bugs.py — doc crank: bugs/README.md (the file-drift catalog)."""
import json, os, sys, time, traceback
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

README = """# bugs/

A **file-drift catalog** — NOT a code-bug tracker. When context drifted, copies of the software
appeared at random locations across the tree; this folder tracks them by content hash so the
duplication/divergence is visible (nothing is moved or deleted).

| file | what |
|---|---|
| `drift_report.json` | content-addressed drift map (`git hash-object` per file). `duplicates` = identical content in >1 location; `version_drift` = one filename with multiple distinct contents. Last scan: ~5163 files, 413 unique blobs, 399 duplicated across locations, 39 filenames with multiple versions. |
| `note-for-claude` | the rationale: find drifted copies, track them via git hashes, catalogue here. |
| `invocators/` | a quarantined drift sample (a stray copy caught during the sweep). |

## How this differs from the other "bug" surfaces (don't confuse them)
| surface | purpose |
|---|---|
| **`bugs/` (here)** | duplicate / divergent **files** across the tree (drift), by content hash. |
| `logs/bug_reports.jsonl` | runtime **Variant-A bug reports** (any tool, on an unexpected outcome). Sink named by `registry.bug_reports`. |
| jazz_telemetry tickets | the live **ticket store** (mechanical ids, `~/.jazz/congruency.sqlite`) — open/close ratchet + audit tickets. |
| `checkouts/current/fixes/index.json` | the **ratchet ledger** — applied fixes/patches (turns). |
| `tooling/congruencey-bugs/bugs.json` | the **CMS's own 15-bug catalog** (the 2006 defects, with repros). |
"""


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
             "traceback": tb.strip().splitlines()[-6:], "note": "doc crank: doc_bugs"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "bugs/README.md",
             "purpose": "doc: bugs/ is the file-drift catalog (vs the other bug/ticket surfaces)",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    open(os.path.join(ROOT, "bugs", "README.md"), "w").write(README)
    record()
    print(json.dumps({"ok": True, "wrote": "bugs/README.md"}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
