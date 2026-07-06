#!/usr/bin/env python3
"""guard_constants.py — crank 2 [refactor #12]: guard boot/Constants_patched.php define()s.

Wrap every `define("X", ...)` with `if (!defined("X"))` so a config loader that pre-defines constants
(CONGRUENCY_SQLITE/ABS_PATH/DB/email) wins WITHOUT PHP redefinition warnings. Test-first via predict.py:
  (1) predict the UNGUARDED file warns on redefinition -> confirm the problem
  (2) apply the guard
  (3) predict the GUARDED file: pre-defs win, ZERO redef warnings -> confirm the fix
A REFUTED prediction halts the crank. Records to fixes/index.json; Variant-A bug report on exception.
"""
import importlib.util
import json
import os
import re
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
CP = os.path.join(SOURCE, "boot", "Constants_patched.php")


def _root(d=HERE):
    while d != os.path.dirname(d):
        if os.path.isfile(os.path.join(d, "registry.json")):
            return d
        d = os.path.dirname(d)
    return os.path.dirname(os.path.dirname(SOURCE))


ROOT = _root()


def _predict_mod():
    p = os.path.join(SOURCE, "tools", "predict.py")
    spec = importlib.util.spec_from_file_location("predict", p)
    m = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(m)
    return m


def _php():
    reg = json.load(open(os.path.join(ROOT, "registry.json")))
    return os.path.join(ROOT, reg.get("paths", {}).get("php", "tooling/congruencey-harness/php/php"))


PHP_TEST = r'''<?php
error_reporting(E_ALL);
define("ABS_PATH", "/CUSTOM_ABS/");
define("MYSQL_STORE_DATABASE", "custom_store");
$warns = array();
set_error_handler(function($n,$s) use (&$warns){ $warns[] = $s; });
require $argv[1];
restore_error_handler();
$redef = 0; foreach ($warns as $w) { if (stripos($w, "already defined") !== false) $redef++; }
echo json_encode(array(
  "abs_ok"   => (ABS_PATH === "/CUSTOM_ABS/"),
  "store_ok" => (defined("MYSQL_STORE_DATABASE") && MYSQL_STORE_DATABASE === "custom_store"),
  "redef_warnings" => $redef,
  "all_ok"   => (ABS_PATH === "/CUSTOM_ABS/" && MYSQL_STORE_DATABASE === "custom_store" && $redef === 0)
));
'''


def php_probe(php, cp_path):
    with tempfile.NamedTemporaryFile("w", suffix=".php", delete=False) as tf:
        tf.write(PHP_TEST)
        t = tf.name
    try:
        r = subprocess.run([php, t, cp_path], capture_output=True, text=True, timeout=30)
        return json.loads((r.stdout or "").strip())
    finally:
        os.remove(t)


def guard():
    pat = re.compile(r'^(\s*)define\(\s*([\'"])([A-Za-z_]\w*)\2')
    out, n = [], 0
    for ln in open(CP).read().split("\n"):
        m = pat.match(ln)
        if m and "!defined" not in ln:
            indent, q, name = m.group(1), m.group(2), m.group(3)
            out.append("%sif (!defined(%s%s%s)) %s" % (indent, q, name, q, ln[len(indent):]))
            n += 1
        else:
            out.append(ln)
    open(CP, "w").write("\n".join(out))
    return n


def bug_report(exc, tb):
    path = os.path.join(ROOT, "logs", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: guard_constants (#12)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record(guarded):
    entry = {"fix": os.path.basename(__file__), "target": "checkouts/current/boot/Constants_patched.php",
             "purpose": "refactor #12: guard %d define()s with if(!defined()) so a config loader's pre-defs win" % guarded,
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    P = _predict_mod()
    php = _php()

    before = php_probe(php, CP)                                  # unguarded
    P.check("unguarded Constants_patched.php emits redefinition warnings when pre-defined",
            expected=True, actual=(before.get("redef_warnings", 0) > 0), open_bug=False)

    guarded = guard()                                            # apply the fix
    if guarded < 40:
        raise RuntimeError("guarded only %d define()s (expected ~46)" % guarded)

    after = php_probe(php, CP)                                   # guarded
    v = P.check("guarded Constants_patched.php: pre-defs win, ZERO redefinition warnings",
                expected=True, actual=bool(after.get("all_ok")))
    if v == "REFUTED":
        raise RuntimeError("guard prediction REFUTED: after=%s" % after)

    record(guarded)
    print(json.dumps({"ok": True, "guarded": guarded, "before": before, "after": after}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
