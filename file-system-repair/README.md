# file-system-repair/

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
