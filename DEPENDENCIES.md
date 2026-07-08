# DEPENDENCIES.md — runtime dependencies & system-process changes

Discipline: never ship a runtime-dependency or system-process change without recording it here (plus a
ledger entry + a prediction). This file is the answer to "did we forget to record a dep/process change?".

## Runtime dependencies
- **PHP 8** (static build, provisioned by `checkouts/current/congruency/tools/provision_php.py`) with **pdo_sqlite**
  + sqlite3 — the CMS runtime. Binary git-ignored at `tooling/congruencey-harness/php/php`.
- The CMS serves via **`php -S boot/router.php`** (PHP built-in server).
- **`boot/router.php` requires `boot/configure.php`** (config-as-data) — merges `constants.default.json`
  + `install.json` and `define()`s the CMS CONSTANTS before the app boots; it also dispatches `?api=` to
  `boot/rest.php` (generic REST over every table, #47).
- The database is a **single sqlite file** (`CONGRUENCY_SQLITE`); path from `install.json` (prod) or the
  relocatable default `state/congruency.sqlite` (dev).

## Build & host environment (recorded from the reference host)
- **Python 3.13.5** (`/usr/bin/python3`). `setup.py` is **stdlib-only** — `argparse, hashlib, json, os,
  signal, subprocess, tarfile, urllib.request, sqlite3` (no third-party packages, no `pip install`).
- **OS:** Linux x86_64, glibc 2.41. **git** must be on PATH at runtime (the installer + tools shell out to it).
- **sqlite:** the Python `sqlite3` module (3.46.x) is present and is what all tooling uses; the **`sqlite3`
  CLI is NOT installed** — inspect the db with `python3 -c "import sqlite3; …"`, not the `sqlite3` command.
- **php:** not on PATH — a static PHP 8 build is **provisioned** by `provision_php.py` to
  `tooling/congruencey-harness/php/php` (git-ignored); no system php is assumed.

## System-process changes
- **State rides in the crank:** each `version-*` tag carries its own `state/database.tar.xz`; `setup.py
  install` extracts it to `state/congruency.sqlite`. There is no separate `state` branch, and the
  production deployer (`deploy.py`) was retired — the lifecycle is `setup.py install|up|down|uninstall`.
- **Config is data:** constants come from `boot/constants.default.json` merged with `install.json` (a flat
  `CONSTANT → value` map); `boot/configure.php` `define()`s them + derives the path constants. There is no
  `Constants_patched.php` any more.
- **The `ext/mysql` shim was retired (#25):** the DAO layer now uses **native PDO** via
  `lib/DatabaseDrivers/MySQL/DataConnection.php` — no `mysql_*` calls remain; `boot/shim.php` survives only
  as a `get_magic_quotes_gpc()` polyfill. `pdo_sqlite` (above) is therefore a hard runtime dep.
- **Test-first + prediction record:** `checkouts/current/tools/predict.py` records prediction↔actual to
  `logs/predictions.jsonl`; a REFUTED prediction opens a jazz bug ticket (component=deploy).
- **Self-hosting ingestion:** the post-commit hook also runs `checkouts/current/tools/ingest_self.py`, which
  mirrors the running source + docs into the CMS DB (content-addressed by git blob hash) so the CMS renders
  itself at `?page=source`/`?page=docs`. Needs `git` at runtime (the hook + tool shell out to it).

## Artifact discipline (no lost/untracked artifacts)
- **Tracked recipes:** `predict.py`, `boot/configure.php` + `boot/constants.default.json`,
  `state/prod_seed.php`, `setup.py` (root). Committed via cranks.
- **Git-ignored runtime artifacts:** the php binary, the sqlite DBs, `logs/*`,
  `checkouts/current/install.json`, `checkouts/current/congruency/fixes/index.json`. Never commit these.
