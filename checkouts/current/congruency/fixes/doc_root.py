#!/usr/bin/env python3
"""doc_root.py — doc crank: root README (structure overview + 3-branch model)."""
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

README = """# congruencey

Steven Peterson's 2006 PHP CMS ("congruency"), resurrected on PHP 8 and folded into a
ratchet-managed package. Design contract in `note-for-claude`: python-only tooling (no shell),
a single config registry, and an auto-filed bug report on any unexpected outcome.

## The 3-branch model
The repo is three single branches — a crank is a **commit + `version-4.05x` tag**, not a branch:

| branch | holds |
|---|---|
| **main** | the **ratchet** — `install.py` only, source-free. Installs a source version and stands the CMS up. |
| **source** | the **CMS + tooling** (this branch). `mint_crank.py` adds a crank = one commit + version tag in place. |
| **state** | the compressed DB (`database.tar.xz` at root), tagged `state-<version>` to match a source version. |

## Layout (the `source` branch)
| path | what |
|---|---|
| `ENTRY_POINT.py` | zero-install tool discovery/runner (finds `checkouts/current/versioning/ops`) |
| `registry.json` | config registry: paths, php provisioning, the `bug_reports` sink — tools throw if they can't see it |
| `note-for-claude` | the design contract |
| `checkouts/current/` | the CMS (`lib/ www/ boot/ bin/`) + the ratchet apparatus (`versioning/ fixes/ tools/ state/`) |
| `checkouts/state/` | a compressed-DB store (legacy; the `state` branch is now authoritative) |
| `tooling/` | test / harness / coverage / ops / bug-catalog tooling |
| `file-system-repair/` | one-time mono tools (`build_inventory`, `assemble_mono_base`) — slated for removal |
| `mcp/` | superseded **Node** MCP snapshot (the live MCP is the Python `~/.MCP` stack) — slated for removal |
| `bugs/` | the content-addressed drift map |
| `logs/` | runtime bug-report sink (`bug_reports.jsonl`, git-ignored) |

## Use
    python3 install.py --version 4.059            # (from main) stand up a source version, verify
    python3 checkouts/current/tools/mint_crank.py --patch P.py --name x   # (on source) mint a crank

More: `checkouts/current/README.md` (the source), `checkouts/current/ARCHITECTURE.md` (CMS internals),
`checkouts/current/tools/README.md` (the ratchet tools), `checkouts/current/versioning/README.md`.
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
             "traceback": tb.strip().splitlines()[-6:], "note": "doc crank: doc_root"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "README.md",
             "purpose": "doc: root README — structure overview + 3-branch model",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    open(os.path.join(ROOT, "README.md"), "w").write(README)
    record()
    print(json.dumps({"ok": True, "wrote": "README.md"}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
