#!/usr/bin/env python3
"""install_config_loader.py — crank 3 [build #13]: wire config_loader.php into router.php.

boot/config_loader.php already exists in the working tree. This patch:
  1. edits boot/router.php to `require config_loader.php` first + guard the CONGRUENCY_SQLITE define,
  2. git-ignores checkouts/current/install.json (a runtime artifact),
  3. TEST-FIRST via predict.py: an install.json with db=/tmp/... makes CONGRUENCY_SQLITE that path;
     with no config, CONGRUENCY_SQLITE stays the relocatable default.
A REFUTED prediction halts. Records to fixes/index.json; Variant-A bug report on exception.
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
ROUTER = os.path.join(SOURCE, "boot", "router.php")

OLD = ("""$BOOT = __DIR__;                                        // checkouts/current/boot\n"""
       """define('CONGRUENCY_SQLITE', dirname($BOOT) . '/state/congruency.sqlite');""")
NEW = ("""$BOOT = __DIR__;                                        // checkouts/current/boot\n"""
       """require $BOOT . '/config_loader.php';                  // load install.json -> CONSTANTS (no-op if absent)\n"""
       """if (!defined('CONGRUENCY_SQLITE')) define('CONGRUENCY_SQLITE', dirname($BOOT) . '/state/congruency.sqlite');""")


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


PROBE = ("<?php $BOOT = $argv[1];"
         " require $BOOT . '/config_loader.php';"
         " if (!defined('CONGRUENCY_SQLITE')) define('CONGRUENCY_SQLITE', dirname($BOOT) . '/state/congruency.sqlite');"
         " echo CONGRUENCY_SQLITE;")


def probe(php, boot_dir, config_path=None):
    with tempfile.NamedTemporaryFile("w", suffix=".php", delete=False) as tf:
        tf.write(PROBE)
        t = tf.name
    env = dict(os.environ)
    env.pop("CONGRUENCY_CONFIG", None)
    if config_path:
        env["CONGRUENCY_CONFIG"] = config_path
    try:
        return subprocess.run([php, t, boot_dir], capture_output=True, text=True, timeout=30, env=env).stdout.strip()
    finally:
        os.remove(t)


def ensure_gitignore():
    gi = os.path.join(ROOT, ".gitignore")
    line = "/checkouts/current/install.json"
    lines = open(gi).read().splitlines() if os.path.isfile(gi) else []
    if line not in lines:
        with open(gi, "a") as fh:
            fh.write(("" if not lines or lines[-1] == "" else "\n") + line + "\n")


def bug_report(exc, tb):
    path = os.path.join(ROOT, "logs", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: install_config_loader (#13)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__),
             "target": "checkouts/current/boot/config_loader.php + router.php",
             "purpose": "build #13: JSON install.json -> CMS CONSTANTS via config_loader (DB path etc.); router requires it first",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    if not os.path.isfile(os.path.join(SOURCE, "boot", "config_loader.php")):
        raise RuntimeError("boot/config_loader.php not present in the working tree")
    rt = open(ROUTER).read()
    if "config_loader.php" not in rt:
        if OLD not in rt:
            raise RuntimeError("router.php anchor not found")
        open(ROUTER, "w").write(rt.replace(OLD, NEW))
    ensure_gitignore()

    P = _predict_mod()
    php = _php()
    boot = os.path.join(SOURCE, "boot")

    # (a) config-driven db path wins
    with tempfile.NamedTemporaryFile("w", suffix=".json", delete=False) as cf:
        cf.write(json.dumps({"db": "/tmp/probe_congruency.sqlite"}))
        cfg = cf.name
    try:
        got = probe(php, boot, cfg)
        v = P.check("install.json db path is pushed into CONGRUENCY_SQLITE",
                    expected="/tmp/probe_congruency.sqlite", actual=got)
        if v == "REFUTED":
            raise RuntimeError("config-driven db REFUTED: got %r" % got)
    finally:
        os.remove(cfg)

    # (b) no config -> relocatable default
    default = os.path.join(SOURCE, "state", "congruency.sqlite")
    got2 = probe(php, boot, None)
    v2 = P.check("no config -> CONGRUENCY_SQLITE is the relocatable default (state/congruency.sqlite)",
                 expected=default, actual=got2)
    if v2 == "REFUTED":
        raise RuntimeError("default-path REFUTED: got %r" % got2)

    record()
    print(json.dumps({"ok": True, "config_driven": got, "default": got2}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
