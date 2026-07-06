#!/usr/bin/env python3
"""pin_port_config.py — crank [build #17]: pin the serving port in the config contract.

config_loader.php now reads "port"/"host" -> CONGRUENCY_PORT / CONGRUENCY_HOST. This patch TEST-FIRST
proves an install.json with port=8891 makes CONGRUENCY_PORT==8891, and adds port/host to DEPLOY.md's
schema. A REFUTED prediction halts. Records to fixes/index.json; Variant-A bug report on exception.
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
LOADER = os.path.join(SOURCE, "boot", "config_loader.php")


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
         " echo defined('CONGRUENCY_PORT') ? CONGRUENCY_PORT : 'UNSET';")

DEPLOY_OLD = ('    { "db": "/srv/site/state/congruency.sqlite",\n'
              '      "abs_path": "/srv/site",                              // optional; default = relocatable\n')
DEPLOY_NEW = ('    { "db": "/srv/site/state/congruency.sqlite",\n'
              '      "port": 8080,                                         // pinned serving port (the launcher binds it)\n'
              '      "host": "0.0.0.0",                                    // optional serving host/interface\n'
              '      "abs_path": "/srv/site",                              // optional; default = relocatable\n')

LOADER_CODE_OLD = """        if (!empty($__c['abs_path']) && !defined('ABS_PATH')) {
            define('ABS_PATH', rtrim(str_replace('\\\\', '/', $__c['abs_path']), '/') . '/');
        }
        if (!empty($__c['site']) && is_array($__c['site'])) {"""
LOADER_CODE_NEW = """        if (!empty($__c['abs_path']) && !defined('ABS_PATH')) {
            define('ABS_PATH', rtrim(str_replace('\\\\', '/', $__c['abs_path']), '/') . '/');
        }
        if (isset($__c['port']) && !defined('CONGRUENCY_PORT')) {
            define('CONGRUENCY_PORT', (int) $__c['port']);
        }
        if (!empty($__c['host']) && !defined('CONGRUENCY_HOST')) {
            define('CONGRUENCY_HOST', $__c['host']);
        }
        if (!empty($__c['site']) && is_array($__c['site'])) {"""

LOADER_DOC_OLD = """     \"abs_path\"  : deploy root override               -> define('ABS_PATH', .../)           [optional]
     \"site\"      : { \"email\": .., \"order_subject\": .. }-> EMAIL_RECIPIANTS / ORDER_SUBJECT_HEADER [optional]"""
LOADER_DOC_NEW = """     \"abs_path\"  : deploy root override               -> define('ABS_PATH', .../)           [optional]
     \"port\"      : serving port                        -> define('CONGRUENCY_PORT', (int))   [pinned for the launcher]
     \"host\"      : serving host/interface              -> define('CONGRUENCY_HOST', ...)     [optional, default 0.0.0.0]
     \"site\"      : { \"email\": .., \"order_subject\": .. }-> EMAIL_RECIPIANTS / ORDER_SUBJECT_HEADER [optional]"""


def probe(php, boot_dir, cfg):
    with tempfile.NamedTemporaryFile("w", suffix=".php", delete=False) as tf:
        tf.write(PROBE)
        t = tf.name
    env = dict(os.environ, CONGRUENCY_CONFIG=cfg)
    try:
        return subprocess.run([php, t, boot_dir], capture_output=True, text=True, timeout=30, env=env).stdout.strip()
    finally:
        os.remove(t)


def bug_report(exc, tb):
    path = os.path.join(ROOT, "logs", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: pin_port_config (#17)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "boot/config_loader.php + DEPLOY.md",
             "purpose": "build #17: pin serving port/host in the config -> CONGRUENCY_PORT/HOST",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    # 1. teach config_loader.php the port/host keys (idempotent)
    loader = open(LOADER).read()
    if "CONGRUENCY_PORT" not in loader:
        if LOADER_CODE_OLD not in loader or LOADER_DOC_OLD not in loader:
            raise RuntimeError("config_loader.php anchors not found")
        loader = loader.replace(LOADER_CODE_OLD, LOADER_CODE_NEW).replace(LOADER_DOC_OLD, LOADER_DOC_NEW)
        open(LOADER, "w").write(loader)

    # update DEPLOY.md schema (idempotent)
    dp = os.path.join(ROOT, "DEPLOY.md")
    if os.path.isfile(dp):
        txt = open(dp).read()
        if '"port"' not in txt:
            if DEPLOY_OLD not in txt:
                raise RuntimeError("DEPLOY.md schema anchor not found")
            open(dp, "w").write(txt.replace(DEPLOY_OLD, DEPLOY_NEW))

    P = _predict_mod()
    php = _php()
    boot = os.path.join(SOURCE, "boot")
    with tempfile.NamedTemporaryFile("w", suffix=".json", delete=False) as cf:
        cf.write(json.dumps({"db": "/tmp/x.sqlite", "port": 8891}))
        cfg = cf.name
    try:
        got = probe(php, boot, cfg)
        v = P.check("install.json port is pinned into CONGRUENCY_PORT", expected="8891", actual=got)
        if v == "REFUTED":
            raise RuntimeError("port pin REFUTED: got %r" % got)
    finally:
        os.remove(cfg)

    record()
    print(json.dumps({"ok": True, "congruency_port": got}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
