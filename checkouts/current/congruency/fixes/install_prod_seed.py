#!/usr/bin/env python3
"""install_prod_seed.py — crank 4 [build #14]: the production stub seed.

state/prod_seed.php already exists. This patch RUNS it into a temp DB and TEST-FIRST asserts (via
predict.py): the required tables exist, Documents == {catalog, invalid} (no dev pages), Products is
empty. A REFUTED prediction halts. Records to fixes/index.json; Variant-A bug report on exception.
"""
import importlib.util
import json
import os
import sqlite3
import subprocess
import sys
import tempfile
import time
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
FIXES = HERE if os.path.basename(HERE) == "fixes" else os.path.dirname(HERE)
SOURCE = os.path.dirname(FIXES)
INDEX = os.path.join(FIXES, "index.json")
PROD_SEED = os.path.join(SOURCE, "state", "prod_seed.php")


def _root(d=HERE):
    while d != os.path.dirname(d):
        if os.path.isfile(os.path.join(d, "registry.json")):
            return d
        d = os.path.dirname(d)
    return os.path.dirname(os.path.dirname(SOURCE))


ROOT = _root()


def _predict_mod():
    spec = importlib.util.spec_from_file_location("predict", os.path.join(SOURCE, "tools", "predict.py"))
    m = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(m)
    return m


def _php():
    reg = json.load(open(os.path.join(ROOT, "registry.json")))
    return os.path.join(ROOT, reg.get("paths", {}).get("php", "tooling/congruencey-harness/php/php"))


def bug_report(exc, tb):
    path = os.path.join(ROOT, "logs", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: install_prod_seed (#14)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "checkouts/current/state/prod_seed.php",
             "purpose": "build #14: fresh production stub DB (intro landing + 404, empty store tables, no dev content)",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    if not os.path.isfile(PROD_SEED):
        raise RuntimeError("state/prod_seed.php not present in the working tree")
    P = _predict_mod()
    php = _php()

    with tempfile.NamedTemporaryFile(suffix=".sqlite", delete=False) as tf:
        db = tf.name
    os.remove(db)  # let prod_seed create it fresh
    try:
        env = dict(os.environ, CONGRUENCY_SQLITE=db)
        r = subprocess.run([php, PROD_SEED], capture_output=True, text=True, timeout=30, env=env)
        if r.returncode != 0 or not os.path.isfile(db):
            raise RuntimeError("prod_seed failed (exit %s): %s" % (r.returncode, (r.stderr or r.stdout)[:300]))

        con = sqlite3.connect(db)
        tables = {x[0] for x in con.execute("SELECT name FROM sqlite_master WHERE type='table'")}
        docs = {x[0] for x in con.execute("SELECT DocumentID FROM Documents")}
        nprod = con.execute("SELECT COUNT(*) FROM Products").fetchone()[0]
        con.close()

        need_tables = {"Document_Templates", "Documents", "Products", "Categories", "Store_Content_Blocks"}
        v1 = P.check("prod stub DB has the required schema", expected=True, actual=need_tables <= tables)
        v2 = P.check("prod stub Documents == {catalog, invalid} (no dev pages)",
                     expected=True, actual=(docs == {"catalog", "invalid"}))
        v3 = P.check("prod stub ships NO products (empty catalog)", expected=0, actual=nprod)
        if "REFUTED" in (v1, v2, v3):
            raise RuntimeError("prod_seed prediction REFUTED: tables=%s docs=%s products=%s" % (tables, docs, nprod))
    finally:
        if os.path.isfile(db):
            os.remove(db)

    record()
    print(json.dumps({"ok": True, "tables": sorted(tables), "documents": sorted(docs), "products": nprod}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
