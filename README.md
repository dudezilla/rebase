# congruencey

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
| `bugs/` | the content-addressed drift map |
| `logs/` | runtime bug-report sink (`bug_reports.jsonl`, git-ignored) |

## Use
    python3 install.py --version 4.059            # (from main) stand up a source version, verify
    python3 checkouts/current/tools/mint_crank.py --patch P.py --name x   # (on source) mint a crank

More: `checkouts/current/README.md` (the source), `checkouts/current/ARCHITECTURE.md` (CMS internals),
`checkouts/current/tools/README.md` (the ratchet tools), `checkouts/current/versioning/README.md`.
