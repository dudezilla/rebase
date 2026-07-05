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

## Rules it obeys (note-for-claude)

- python only — no shell scripts;
- **registry-gated** — throws if it cannot see `registry.json`;
- all paths derived from `__file__` (relocatable);
- `$CONGRUENCEY_PHP` overrides the php location;
- auto bug-report (timestamped) to the registry's `bug_reports` sink on any exception.
