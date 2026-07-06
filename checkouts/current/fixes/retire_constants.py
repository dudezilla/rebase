#!/usr/bin/env python3
"""retire_constants.py — crank #20: retire Constants_patched.php + config_loader.php for configure.php.

boot/constants.default.json (data) + boot/configure.php (one loop over the data + computed derived
constants) already exist in the working tree. This patch rewires router.php to require configure.php,
writes install.example.json + a DEPLOY.md note, PARITY-tests that configure.php reproduces every
constant the old Constants_patched.php defined (same values, except the EMAIL_RECIPIANTS placeholder),
proves an install.json override wins, then git-rm's the two retired files. Test-first via predict.py;
halt on REFUTED. Records to fixes/index.json; Variant-A bug report on exception.
"""
import importlib.util
import json
import os
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
BOOT = os.path.join(SOURCE, "boot")
ROUTER = os.path.join(BOOT, "router.php")


def _root(d=HERE):
    while d != os.path.dirname(d):
        if os.path.isfile(os.path.join(d, "registry.json")):
            return d
        d = os.path.dirname(d)
    return os.path.dirname(os.path.dirname(SOURCE))


ROOT = _root()
HARNESS = ("<?php error_reporting(0); ini_set('display_errors','0');"
           " require $argv[1]; echo json_encode(get_defined_constants(true)['user']);")


def git(*a):
    return subprocess.run(["git", *a], cwd=ROOT, capture_output=True, text=True)


def _predict_mod():
    spec = importlib.util.spec_from_file_location("predict", os.path.join(SOURCE, "tools", "predict.py"))
    m = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(m)
    return m


def _php():
    reg = json.load(open(os.path.join(ROOT, "registry.json")))
    return os.path.join(ROOT, reg.get("paths", {}).get("php", "tooling/congruencey-harness/php/php"))


def dump_constants(php, target_php, config=None):
    with tempfile.NamedTemporaryFile("w", suffix=".php", delete=False) as tf:
        tf.write(HARNESS)
        h = tf.name
    env = dict(os.environ)
    env["CONGRUENCY_CONFIG"] = config if config is not None else "/no-such-congruency-config"
    try:
        r = subprocess.run([php, h, target_php], capture_output=True, text=True, timeout=30, env=env)
        return json.loads(r.stdout or "{}")
    finally:
        os.remove(h)


def edit_router():
    lines = open(ROUTER).read().split("\n")
    out, skip = [], False
    for ln in lines:
        if "config_loader.php" in ln:
            continue
        if "define('CONGRUENCY_SQLITE'" in ln and "dirname($BOOT)" in ln:
            continue
        if ln.startswith("require $BOOT . '/Constants_patched.php'"):
            out.append("require $BOOT . '/configure.php';                       // install.json + defaults -> ALL constants")
            continue
        if ln.strip().startswith("foreach (["):
            skip = True
            continue
        if skip:
            if "] as $k => $v)" in ln:
                skip = False
            continue
        out.append(ln)
    open(ROUTER, "w").write("\n".join(out))


EXAMPLE = {
    "CONGRUENCY_SQLITE": "/srv/site/state/congruency.sqlite",
    "CONGRUENCY_PORT": 8080,
    "CONGRUENCY_HOST": "0.0.0.0",
    "EMAIL_RECIPIANTS": "admin@example.com",
    "ORDER_SUBJECT_HEADER": "You have a new order",
    "MYSQL_STORE_DATABASE": "CONGRUENCY_STORE",
    "USELOG_DEBUG": False,
}
DEPLOY_NOTE = """

## Configuration is data (`configure.php` + `constants.default.json`)
The CMS constants are DATA: `boot/constants.default.json` holds the defaults; `install.json` (a flat map
`{ "CONSTANT_NAME": value, ... }`) overrides any of them. `boot/configure.php` loops over the merged data
and `define()`s each constant, then computes the derived path constants (`ABS_PATH`, `TAGS_DIR`, `LIB`,
`BIN`, `ETC`, `CLASS_LOADER_HEADER`, …). There is no `Constants_patched.php` any more. A minimal install:
    { "CONGRUENCY_SQLITE": "/srv/site/state/congruency.sqlite", "CONGRUENCY_PORT": 8080 }
Every key in `constants.default.json` is overridable; see `install.example.json`.
"""


def bug_report(exc, tb):
    path = os.path.join(ROOT, "logs", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: retire_constants (#20)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__),
             "target": "boot/configure.php + constants.default.json (retire Constants_patched.php + config_loader.php)",
             "purpose": "build #20: config-as-data — one loop over JSON constants + computed derived; delete the two old PHP config files",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    for f in ("configure.php", "constants.default.json"):
        if not os.path.isfile(os.path.join(BOOT, f)):
            raise RuntimeError("boot/%s not present in the working tree" % f)
    P = _predict_mod()
    php = _php()

    # PARITY: old Constants_patched.php vs new configure.php (before deleting the old one)
    old = dump_constants(php, os.path.join(BOOT, "Constants_patched.php"))
    new = dump_constants(php, os.path.join(BOOT, "configure.php"))
    missing = [k for k in old if k not in new]
    changed = {k: (old[k], new[k]) for k in old
               if k in new and old[k] != new[k] and k != "EMAIL_RECIPIANTS"}

    # override wins
    with tempfile.NamedTemporaryFile("w", suffix=".json", delete=False) as cf:
        cf.write(json.dumps({"MYSQL_STORE_DATABASE": "XYZ_TEST"}))
        ov = cf.name
    try:
        newov = dump_constants(php, os.path.join(BOOT, "configure.php"), config=ov)
    finally:
        os.remove(ov)

    verdicts = [
        P.check("configure.php loses NO constant that Constants_patched.php defined", expected=0, actual=len(missing)),
        P.check("configure.php changes no constant value (except the email placeholder)", expected=0, actual=len(changed)),
        P.check("EMAIL_RECIPIANTS default is now the placeholder", expected="admin@example.com", actual=new.get("EMAIL_RECIPIANTS")),
        P.check("install.json override wins over the default data", expected="XYZ_TEST", actual=newov.get("MYSQL_STORE_DATABASE")),
        P.check("derived TAGS_DIR is still computed", expected=True, actual=str(new.get("TAGS_DIR", "")).endswith("invocators/tags/")),
    ]
    if "REFUTED" in verdicts:
        raise RuntimeError("REFUTED: missing=%s changed=%s email=%r override=%r" % (
            missing[:8], list(changed.items())[:8], new.get("EMAIL_RECIPIANTS"), newov.get("MYSQL_STORE_DATABASE")))

    # rewire router, write example + doc, then retire the old files
    edit_router()
    with open(os.path.join(SOURCE, "install.example.json"), "w") as fh:
        json.dump(EXAMPLE, fh, indent=2)
        fh.write("\n")
    dp = os.path.join(ROOT, "DEPLOY.md")
    if os.path.isfile(dp) and "Configuration is data" not in open(dp).read():
        with open(dp, "a") as fh:
            fh.write(DEPLOY_NOTE)
    git("rm", "--quiet", "checkouts/current/boot/Constants_patched.php", "checkouts/current/boot/config_loader.php")

    record()
    print(json.dumps({"ok": True, "old_count": len(old), "new_count": len(new),
                      "missing": missing, "changed": list(changed)}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
