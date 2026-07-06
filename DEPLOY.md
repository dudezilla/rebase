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

## install.json (config pushed into CONSTANTS)
    { "db": "/srv/site/state/congruency.sqlite",
      "abs_path": "/srv/site",                              // optional; default = relocatable
      "site": { "email": "...", "order_subject": "..." },   // optional
      "constants": { "MYSQL_SERVER": "..." } }              // optional (real-MySQL deploy)
`boot/config_loader.php` reads it (env `$CONGRUENCY_CONFIG` overrides the path) and `define()`s the
constants before the app boots. Under the sqlite shim only `db` (CONGRUENCY_SQLITE) actually matters.

## The production stub (state/prod_seed.php)
A fresh, functional-but-empty starter: a landing/intro (keyed `catalog`, the Controller default) + the
mandatory `invalid` 404, current Georgia styling, empty store tables. NO dev content (no bug pages, no
demo products/order-wizard). Add your pages/catalog/features on top.

See `DEPENDENCIES.md` for runtime deps + process changes, and `checkouts/current/ARCHITECTURE.md` for the
CMS internals.
