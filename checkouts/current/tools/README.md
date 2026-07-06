# tools/ — the congruency php-web harness

Reconstruction of the old `~/congruencey-harness` as the source's own tool (the reference
lived off-tree and is gone; only pieces survived under `boot/` and the folded
`tooling/congruencey-harness/`). This folder holds the **php-web reference server** (`serve.py`) for the
congruency source at `checkouts/current`, the **regression gates** the ratchet runs (below), the
**commit observer** (`doc_watch.py`), and the crank/ledger apparatus (`predict.py`, `make_state.py`,
`mint_crank.py`, `provision_php.py`).

## serve.py — one python script, no shell

Boots the CMS under PHP's built-in web server with the relocatable new-tree bootstrap
`../boot/router.php` (shim → configure.php → AutoLoader → Controller), served from the
source root.

```
python3 tools/serve.py            # serve on 0.0.0.0:8899   (Ctrl-C to stop)
python3 tools/serve.py --port 9000
python3 tools/serve.py --verify   # boot, probe catalog/about, assert HTTP 200, exit
```

Prerequisites (both ship as recipes; artifacts are git-ignored, not committed):

- **PHP** at `tooling/congruencey-harness/php/php` — `python3 tools/provision_php.py`
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

## Gates the ratchet runs

Every crank is verified against these (exit non-zero on failure):

- **`tagcheck.py`** — renders every invocator tag standalone (`?page=tags&tag=NAME`), asserts HTTP 200 with
  no PHP fatal **or warning/notice** (24/24). `python3 tools/tagcheck.py [--base URL] [--report out.json]`.
- **`crawl.py`** — BFS web-spider over every link on every page; reports broken links (the `?api=Documents`
  oracle supplies the valid-page set; the deliberate `?page=nope` is the expected `broken=1`).
- **`tostring_check.py`** — flags `__toString()` methods that can return `null` (the #60 class).
- **`gpl_stamp.py`** — keeps the GPL short-notice header on every first-party `.php` (`--check` / `--fix`,
  line-ending preserving).
- **`deploy_lifecycle_check.py`** — drives `deploy.py` through ON → SERVE → OFF → REDEPLOY in an isolated
  git worktree, leaving your branch untouched (the #26 gate; 4/4 transitions).
- **`formelement_roundtrip.php`** — asserts each form element's `to_array()`/`from_array()` round-trips
  through JSON, plus per-element validation + render (the #44/#45 gate). Run with the provisioned php:
  `tooling/congruencey-harness/php/php tools/formelement_roundtrip.php`.

## Rules it obeys (note-for-claude)

- python only — no shell scripts;
- **registry-gated** — throws if it cannot see `registry.json`;
- all paths derived from `__file__` (relocatable);
- `$CONGRUENCEY_PHP` overrides the php location;
- auto bug-report (timestamped) to the registry's `bug_reports` sink on any exception.
