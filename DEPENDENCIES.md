# DEPENDENCIES.md — runtime dependencies & system-process changes

Discipline: never ship a runtime-dependency or system-process change without recording it here (plus a
ledger entry + a prediction). This file is the answer to "did we forget to record a dep/process change?".

## Runtime dependencies
- **PHP 8** (static build, provisioned by `checkouts/current/tools/provision_php.py`) with **pdo_sqlite**
  + sqlite3 — the CMS runtime. Binary git-ignored at `tooling/congruencey-harness/php/php`.
- The CMS serves via **`php -S boot/router.php`** (PHP built-in server).
- **`boot/router.php` now requires `boot/config_loader.php`** — loads the install config JSON into the
  CMS CONSTANTS before the app boots.
- The database is a **single sqlite file** (`CONGRUENCY_SQLITE`); path from `install.json` (prod) or the
  relocatable default `state/congruency.sqlite` (dev).

## System-process changes
- **Production deploy** (`deploy.py`) CREATES the target folder + `install.json` + `state/` + a fresh stub
  DB when the target has no config (folder-creation on missing config).
- **Two databases:** dev/test/inflection use the demo DB on the `state` branch (`install.py --version`);
  production uses a FRESH stub DB per deploy (`deploy.py` + `state/prod_seed.php`).
- **`boot/Constants_patched.php`** define()s are now `if(!defined())`-guarded so `config_loader.php` can
  override (DB path, ABS_PATH, email).
- **Test-first + prediction record:** `checkouts/current/tools/predict.py` records prediction↔actual to
  `logs/predictions.jsonl`; a REFUTED prediction opens a jazz bug ticket (component=deploy).

## Artifact discipline (no lost/untracked artifacts)
- **Tracked recipes:** `predict.py`, `boot/config_loader.php`, `state/prod_seed.php` (source); `deploy.py`,
  `install.py` (main). Committed via cranks + recorded in `checkouts/current/fixes/index.json`.
- **Git-ignored runtime artifacts:** the php binary, the sqlite DBs, `logs/*.jsonl`,
  `checkouts/current/install.json`, and deploy target folders (external). Never commit these.
