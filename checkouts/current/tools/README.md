# tools/ — the congruency php-web harness

Reconstruction of the old `~/congruencey-harness` as the source's own tool (the reference
lived off-tree and is gone; only pieces survived under `boot/` and the folded
`tooling/congruencey-harness/`). This folder is the **php-web reference server** for the
congruency source at `checkouts/current`.

## serve.py — one python script, no shell

Boots the CMS under PHP's built-in web server with the relocatable new-tree bootstrap
`../boot/router.php` (shim → Constants_patched → AutoLoader → Controller), served from the
source root.

```
python3 tools/serve.py            # serve on 0.0.0.0:8899   (Ctrl-C to stop)
python3 tools/serve.py --port 9000
python3 tools/serve.py --verify   # boot, probe catalog/about, assert HTTP 200, exit
```

Prerequisites (both ship as recipes; artifacts are git-ignored, not committed):

- **PHP** at `tooling/congruencey-harness/php/php` — `python3 file-system-repair/provision_php.py`
- **state DB** at `checkouts/current/state/congruency.sqlite` — auto-extracted from
  `state/database.tar.xz` on first boot (or `python3 fixes/install_state_db.py`).

## doc_watch.py — auto-file "documentation may be stale" tickets

An outside-the-app observer of commits. For every commit where a **script (`.php`/`.py`) changed but
no doc (`.md`/`.txt`) was touched in that same commit**, it maps the changed code area → the specific
doc(s) likely now stale (a built-in dir→doc map) and opens a `documentation` ticket in the unified jazz
tracker (`~/.jazz/congruency.sqlite`). One OPEN ticket per doc — deduped by `meta.doc`, and each commit
recorded once in `meta.commits`, so the standalone tool and the git hook converge without duplicates.

```
python3 tools/doc_watch.py                 # watermark..HEAD  (HEAD only on first run)
python3 tools/doc_watch.py --since HEAD~10  # batch catch-up over a range
python3 tools/doc_watch.py --commit <sha>   # inspect one commit
python3 tools/doc_watch.py --dry-run        # classify + print, write nothing
python3 tools/doc_watch.py --install-hook   # drop a post-commit hook that runs --head
```

Filing goes through `jazz_telemetry.open_ticket(..., component="documentation")` (the same helper
`predict.py`/`mint_crank.py` use). `--install-hook` drops a best-effort `post-commit` hook (python,
never blocks a commit). The watermark lives at `logs/doc_watch.json` (git-ignored); correctness comes
from `meta.commits`, so a lost watermark only re-scans, never double-files.

## Rules it obeys (note-for-claude)

- python only — no shell scripts;
- **registry-gated** — throws if it cannot see `registry.json`;
- all paths derived from `__file__` (relocatable);
- `$CONGRUENCEY_PHP` overrides the php location;
- auto bug-report (timestamped) to the registry's `bug_reports` sink on any exception.
