#!/usr/bin/env python3
"""phase3_cleanup.py — crank (bug #9 phase 3 / ticket #10): remove the now-dead folders + dedup DB.

ONE change: remove what the migration + 3-branch model made vestigial (verified: no live readers).
  - file-system-repair/  (build_inventory, assemble_mono_base, repo_snapshot, README — one-time)
  - mcp/                 (superseded Node MCP snapshot; live MCP is ~/.MCP)
  - checkouts/state/     (bare DB store, superseded by the authoritative `state` branch)
  - registry.json        (drop the dead `file_system_repair` block)
  - README.md            (drop the two removed rows)
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
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: phase3_cleanup (#10)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record(removed):
    entry = {"fix": os.path.basename(__file__), "target": ", ".join(removed),
             "purpose": "phase 3 (#10): remove vestigial file-system-repair/ + mcp/ + bare checkouts/state/; drop dead registry block",
             "removed": removed, "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    removed = []
    for rel in ("file-system-repair", "mcp", os.path.join("checkouts", "state")):
        p = os.path.join(ROOT, rel)
        if os.path.isdir(p):
            shutil.rmtree(p)
            removed.append(rel)
    if not removed:
        raise RuntimeError("nothing to remove — expected file-system-repair/ + mcp/ + checkouts/state/")

    # drop the dead file_system_repair block from the registry
    regp = os.path.join(ROOT, "registry.json")
    reg = json.load(open(regp))
    if "file_system_repair" in reg:
        del reg["file_system_repair"]
        with open(regp, "w") as fh:
            json.dump(reg, fh, indent=2)
            fh.write("\n")

    # drop the two removed rows from the root README layout table
    rp = os.path.join(ROOT, "README.md")
    if os.path.isfile(rp):
        keep = [ln for ln in open(rp).read().splitlines(keepends=True)
                if not (ln.startswith("| `file-system-repair/`") or ln.startswith("| `mcp/`"))]
        open(rp, "w").write("".join(keep))

    record(removed)
    print(json.dumps({"ok": True, "removed": removed, "registry_block_dropped": True}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
