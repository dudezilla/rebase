#!/usr/bin/env python3
"""doc_apparatus.py — doc crank: versioning/README.md + fixes/README.md (ratchet apparatus)."""
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

VERSIONING = """# checkouts/current/versioning/

The python **versioning apparatus** (discovered by the repo-root `ENTRY_POINT.py`, which puts this
dir on `sys.path` and runs its `ops`). Principle: versions are **python-computed, never inferred**;
every git op is instrumented/logged.

| file | what |
|---|---|
| `version_source.py` | `compute_next_version(git, repo)` — next `major.NNN` from live `version-*` tags. **The ratchet's `mint_crank` reuses this** for its lean commit+tag. |
| `ops/` | importable operations (`commit_release`, `refresh_bundles`, `verify_bundles`, `generate_manifest`, `git_commit`, `git_push`, …); each exposes `run(**kwargs)` and auto-bug-reports. Run via `ENTRY_POINT.py --op NAME --args '{...}'`. |
| `gitutil.py` | instrumented `Git` client (write ops require an identity; everything logged). |
| `paths.py` | derives repo/entry/bundles paths from `__file__` + git (no hard-coded absolutes). |
| `checkout.py` | bundle-based checkout (clones from local `*.bundle` files, detaches at a version tag). |
| `verify_git_state.py`, `push_branch.py` | integrity check; path-remote branch propagation. |

**Note:** the ratchet mints with a **lean single-repo** commit+tag (`compute_next_version` + `git tag`),
NOT `commit_release` — `commit_release` scans the *parent* for module repos (`collect_modules`), which
doesn't fit this single folded repo (it would find no modules, or reach sibling packages).
"""

FIXES_README = """# checkouts/current/fixes/

The **ratchet ledger** and the crank patches. Each patch is ONE self-contained python script that
makes ONE change, following the note-for-claude contract: registry-gated, self-verifying, upserts an
entry into `index.json` on success, and files a Variant-A bug report on any exception.

| item | what |
|---|---|
| `index.json` | the append-only **ratchet ledger** (dedup by `fix`): every applied fix/patch + purpose + timestamp. |
| `examples/turn_example.py` | the **patch template** — copy it, put your one change in `main()`, mint it. |
| turns 1–6 (`fix_versioner_major4_minor3`, `prove_git_store`, `repath_to_new_tree`, `install_state_db`, `boot_www`) | the original "ratchet link" fixes that stood the CMS up. |
| crank patches (`fix_make_state_determinism`, `fix_provision_php_idempotent`, `migrate_provision_php`, `doc_*`) | later cranks, each captured as a `version-4.05x`. |

## How a crank uses this dir
`checkouts/current/tools/mint_crank.py --patch P.py --name x` copies `P.py` here as `fixes/x.py`,
runs it (the one change), then **commits + tags** `version-4.05x` on `source` in place (no branch),
snapshots state, and verifies. Every step records a bug event on any unexpected outcome.
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
             "traceback": tb.strip().splitlines()[-6:], "note": "doc crank: doc_apparatus"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "versioning/README.md, fixes/README.md",
             "purpose": "doc: the ratchet apparatus (versioner + fixes/patch ledger)",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    cur = os.path.join(ROOT, "checkouts", "current")
    open(os.path.join(cur, "versioning", "README.md"), "w").write(VERSIONING)
    open(os.path.join(cur, "fixes", "README.md"), "w").write(FIXES_README)
    record()
    print(json.dumps({"ok": True, "wrote": ["versioning/README.md", "fixes/README.md"]}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
