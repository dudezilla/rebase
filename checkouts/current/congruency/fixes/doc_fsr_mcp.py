#!/usr/bin/env python3
"""doc_fsr_mcp.py — crank patch (Phase 1): document file-system-repair/ and mcp/.

ONE change: write file-system-repair/README.md and mcp/README.md explaining what each tool does,
which is load-bearing vs one-time vs vestigial. Answers "what do these do?" and de-risks the
later migration/removal. Self-records to fixes/index.json; Variant-A bug report on exception.
"""
import json
import os
import sys
import time
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))          # fixes/ (when injected)
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

FSR_README = """# file-system-repair/

Bootstrap + repair tooling for the mono. Python-only, **registry-gated** (each tool walks up to
`registry.json` and throws if it can't see it) and each **auto-files a timestamped bug report** on
exception (the note-for-claude contract).

| tool | what it does | status |
|---|---|---|
| `provision_php.py` | Provisions the PHP runtime: tries registry-listed local sources, else **network-fetches** a static PHP 8.4 build, verifies `php -v`, and git-ignores the binary at `tooling/congruencey-harness/php/php`. | **Load-bearing** — `install.py`'s "provision php" step. *Being migrated to `checkouts/current/tools/`.* |
| `build_inventory.py` | Scans the tree and writes a **content-addressed** manifest (`git hash-object` per file) to `repo_snapshot.json`. | One-time inventory. |
| `assemble_mono_base.py` | Folds `file-system-repair/` into the `b01` mono and asserts the required 8-dir base. | One-time mono-assembly. |

## Bug-report sink
The registry key `bug_reports` names the JSONL sink every tool appends to on failure (Variant-A:
`filename` · `function` · `time-of-occurance` · `methods-to-reproduce` · `possible-cause` ·
`traceback`). It historically lived here as `file-system-repair/bug_reports.jsonl`; it is being
**de-anchored** to a neutral `logs/bug_reports.jsonl` so code stops hardcoding this folder (bug #9).

## Status
Once `provision_php.py` works from `checkouts/current/tools/` and the sink is de-anchored, the
one-time tools and this folder are slated for removal (a later "circle-back" crank).
"""

MCP_README = """# mcp/

**Vestigial** — a folded snapshot of the *old Node* MCP implementation. The **running** MCP is the
zero-dependency **Python** stack under `~/.MCP` (gate → coupler → `congruency-mcp/server.py` …);
nothing live references this folded copy.

| dir | what it is | status |
|---|---|---|
| `congruency-mcp/` (`server.js`, `tools/telemetry.php`, `package.json`) | Node MCP server exposing `run_verify` / `list_bugs` / `reproduce_bug` / `query_telemetry`; resolves `CONGRUENCY_ROOT` to the tree (tolerant of the `congruencey-*` spelling). | Superseded by `~/.MCP/congruency-mcp/server.py`. |
| `mcp-coupler/` (`coupler.js`, `coupler.config.json`) | Node MCP aggregator that fronts N downstream servers as one namespaced server. | Superseded by `~/.MCP/coupler.py`. |

## Status
Kept only as a reference snapshot; slated for removal (a later "circle-back" crank). The live MCP
config + servers are in `~/.MCP` (outside this repo).
"""


def bug_report(exc, tb):
    # de-anchored: prefer registry bug_reports, else logs/ (never hardcode file-system-repair)
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
             "traceback": tb.strip().splitlines()[-6:], "note": "crank patch: doc_fsr_mcp"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "file-system-repair/README.md, mcp/README.md",
             "purpose": "doc: what file-system-repair + mcp do (phase 1 of the migration)",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    fsr = os.path.join(ROOT, "file-system-repair")
    mcp = os.path.join(ROOT, "mcp")
    if not os.path.isdir(fsr) or not os.path.isdir(mcp):
        raise RuntimeError("expected file-system-repair/ and mcp/ to exist (fsr=%s mcp=%s)"
                           % (os.path.isdir(fsr), os.path.isdir(mcp)))
    open(os.path.join(fsr, "README.md"), "w").write(FSR_README)
    open(os.path.join(mcp, "README.md"), "w").write(MCP_README)
    record()
    print(json.dumps({"ok": True, "wrote": ["file-system-repair/README.md", "mcp/README.md"]}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        tb = traceback.format_exc()
        bug_report(exc, tb)
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
