# checkouts/current/versioning/

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
