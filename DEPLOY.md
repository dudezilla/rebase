# DEPLOY.md — production deployment

Two installers live on `main`:
- **`install.py --version X`** — DEV/TEST: stands up a source version against the demo DB (the ratchet).
- **`deploy.py --target DIR --version X`** — PRODUCTION: deploys a source version to a target folder with a
  FRESH stub DB + a JSON config.

## deploy.py
    python3 deploy.py --target /srv/site --version 4.070
Checks out `version-X`, exports the app (minus the ratchet apparatus + dev DB) into the target, writes
`<target>/install.json`, seeds a fresh production stub DB, and boots config-driven — verifying the stub
site is up and dev content is gone (recorded as predictions; a REFUTED prediction halts). Creates the
target + config if absent.

## install.json (config-as-data — a flat map of CONSTANT → value)
    { "CONGRUENCY_SQLITE": "/srv/site/state/congruency.sqlite",   // the DB path (the one that matters)
      "CONGRUENCY_PORT": 8080,                                     // pinned serving port
      "CONGRUENCY_HOST": "0.0.0.0" }                               // optional serving host/interface
`boot/configure.php` merges it over `boot/constants.default.json` and `define()`s each constant before
the app boots (env `$CONGRUENCY_CONFIG` overrides the install.json path). `deploy.py` writes exactly these
`CONGRUENCY_*` keys. To deploy against real MySQL instead of the sqlite target, set the `MYSQL_*`
constants and give `DataConnection` a `mysql:` DSN — the DAOs are driver-agnostic since the native-PDO
migration (#25); under sqlite only `CONGRUENCY_SQLITE` matters.

## The production stub (state/prod_seed.php)
A fresh, functional-but-empty starter: a landing/intro (keyed `catalog`, the Controller default) + the
mandatory `invalid` 404, current Georgia styling, empty store tables. NO dev content (no bug pages, no
demo products/order-wizard). Add your pages/catalog/features on top.

See `DEPENDENCIES.md` for runtime deps + process changes, and `checkouts/current/ARCHITECTURE.md` for the
CMS internals.

## Self-hosting archive (admin)
`?page=source` / `?page=docs` render the CMS's own source + docs from four tables
(`code_blobs`/`code_refs`/`doc_blobs`/`doc_refs`) that `tools/ingest_self.py` populates from the git tree on
every crank (post-commit hook), content-addressed by git blob hash. On a fresh production target the tables
are empty until `ingest_self.py` runs there; the pages are admin-only and the tables are excluded from the
public REST.


## Configuration is data (`configure.php` + `constants.default.json`)
The CMS constants are DATA: `boot/constants.default.json` holds the defaults; `install.json` (a flat map
`{ "CONSTANT_NAME": value, ... }`) overrides any of them. `boot/configure.php` loops over the merged data
and `define()`s each constant, then computes the derived path constants (`ABS_PATH`, `TAGS_DIR`, `LIB`,
`BIN`, `ETC`, `CLASS_LOADER_HEADER`, …). There is no `Constants_patched.php` any more. A minimal install:
    { "CONGRUENCY_SQLITE": "/srv/site/state/congruency.sqlite", "CONGRUENCY_PORT": 8080 }
Every key in `constants.default.json` is overridable; see `install.example.json`.
