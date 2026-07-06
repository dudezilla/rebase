#!/usr/bin/env python3
"""doc_deploy_deps.py — crank 6 [build/doc #16]: DEPENDENCIES.md + DEPLOY.md."""
import json, os, sys, time, traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
FIXES = HERE if os.path.basename(HERE) == "fixes" else os.path.dirname(HERE)
SOURCE = os.path.dirname(FIXES)
INDEX = os.path.join(FIXES, "index.json")


def _root(d=HERE):
    while d != os.path.dirname(d):
        if os.path.isfile(os.path.join(d, "registry.json")):
            return d
        d = os.path.dirname(d)
    return os.path.dirname(os.path.dirname(SOURCE))


ROOT = _root()

DEPS = """# DEPENDENCIES.md — runtime dependencies & system-process changes

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
"""

DEPLOY = """# DEPLOY.md — production deployment

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
"""


def bug_report(exc, tb):
    path = os.path.join(ROOT, "logs", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: doc_deploy_deps (#16)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "DEPENDENCIES.md, DEPLOY.md",
             "purpose": "build/doc #16: record runtime deps + process changes; document deploy.py + install.json + stub",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    open(os.path.join(ROOT, "DEPENDENCIES.md"), "w").write(DEPS)
    open(os.path.join(ROOT, "DEPLOY.md"), "w").write(DEPLOY)
    record()
    print(json.dumps({"ok": True, "wrote": ["DEPENDENCIES.md", "DEPLOY.md"]}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
