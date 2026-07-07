#!/usr/bin/env python3
"""retire_www_etc_constants.py — crank #21: retire the dormant www/Constants.php + etc/Constants.php.

Same treatment as Constants_patched.php: configure.php is the single constants source. etc/Constants.php
has no code consumer (delete). www/Constants.php is required by www/index.php — rewire that require to
boot/configure.php (which also fixes www/index.php's stale hardcoded /web/web/congruency ABS_PATH), then
delete www/Constants.php. Test-first via predict.py; halt on REFUTED. Records to fixes/index.json;
Variant-A bug report on exception.
"""
import importlib.util
import json
import os
import subprocess
import sys
import time
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
FIXES = HERE if os.path.basename(HERE) == "fixes" else os.path.dirname(HERE)
SOURCE = os.path.dirname(FIXES)
INDEX = os.path.join(FIXES, "index.json")
INDEX_PHP = os.path.join(SOURCE, "www", "index.php")


def _root(d=HERE):
    while d != os.path.dirname(d):
        if os.path.isfile(os.path.join(d, "registry.json")):
            return d
        d = os.path.dirname(d)
    return os.path.dirname(os.path.dirname(SOURCE))


ROOT = _root()
OLD_REQUIRE = 'require_once("Constants.php");'
NEW_REQUIRE = 'require_once(__DIR__ . "/../boot/configure.php");'


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


def bug_report(exc, tb):
    path = os.path.join(ROOT, "logs", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: retire_www_etc_constants (#21)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "www/Constants.php + etc/Constants.php (retired); www/index.php rewired",
             "purpose": "build #21: retire the dormant www/ + etc/ Constants.php copies; configure.php is the sole constants source",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    # 1. rewire www/index.php's constants require -> configure.php
    txt = open(INDEX_PHP).read()
    if "configure.php" not in txt:
        if OLD_REQUIRE not in txt:
            raise RuntimeError("www/index.php require anchor not found")
        open(INDEX_PHP, "w").write(txt.replace(OLD_REQUIRE, NEW_REQUIRE))

    # 2. retire the two dormant Constants copies
    for rel in ("checkouts/current/www/Constants.php", "checkouts/current/etc/Constants.php"):
        if os.path.isfile(os.path.join(ROOT, rel)):
            git("rm", "--quiet", rel)

    P = _predict_mod()
    php = _php()
    gone = [r for r in ("www/Constants.php", "etc/Constants.php")
            if not os.path.isfile(os.path.join(SOURCE, r))]
    # the hardcoded DB-constant define-list now lives nowhere in the source (only JSON data);
    # exclude the fixes/ crank-ledger, whose patch scripts quote define(...) strings in test code.
    hardcoded = [x for x in git("grep", "-lF", 'define("MYSQL_STORE_DATABASE"',
                                 "--", "checkouts/current", ":!checkouts/current/fixes").stdout.split() if x]
    lint_ok = subprocess.run([php, "-l", INDEX_PHP], capture_output=True).returncode == 0

    verdicts = [
        P.check("www/Constants.php + etc/Constants.php are retired", expected=2, actual=len(gone)),
        P.check("no PHP file hardcodes the MYSQL_* define-list any more (config is JSON data)", expected=0, actual=len(hardcoded)),
        P.check("www/index.php still parses after the rewire (php -l)", expected=True, actual=lint_ok),
        P.check("www/index.php now sources constants from configure.php", expected=True, actual=("configure.php" in open(INDEX_PHP).read())),
    ]
    if "REFUTED" in verdicts:
        raise RuntimeError("REFUTED: gone=%s hardcoded=%s lint_ok=%s" % (gone, hardcoded, lint_ok))

    record()
    print(json.dumps({"ok": True, "retired": gone, "hardcoded_left": hardcoded}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
