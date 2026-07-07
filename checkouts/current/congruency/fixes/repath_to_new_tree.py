#!/usr/bin/env python3
"""repath_to_new_tree.py — ratchet fix: make the CMS boot from its NEW path.

The checkout's server config points at dead OLD-tree paths:
  - serve_override.php: ABS_PATH -> /home/.../congruency/2a2e4f8.../  (missing)
  - www/Constants.php / etc/Constants.php: ABS_PATH -> /web/web/congruency/ (missing)
and CONGRUENCY_SQLITE is defined nowhere in the checkout.

Per spec the previous (harness) copy is the REFERENCE and we change only pathing.
This fix materializes a self-contained, RELOCATABLE boot dir inside the source:
  checkouts/current/boot/{shim.php, AutoLoader.php, Constants_patched.php, router.php}
ABS_PATH and CONGRUENCY_SQLITE are computed from __DIR__ — no hard-coded path, so the
tree works wherever it lives. serve_override.php is also repathed relocatably.

Reads the harness reference files read-only; writes only inside checkouts/current.
python-only, recorded.
"""
import json
import os
import sys
import time
import traceback

HERE = os.path.dirname(os.path.abspath(__file__))
SOURCE = os.path.dirname(HERE)                                   # checkouts/current
MONO = os.path.dirname(os.path.dirname(SOURCE))                 # b01
BOOT = os.path.join(SOURCE, "boot")
INDEX = os.path.join(HERE, "index.json")
BUGS = os.path.join(MONO, "logs", "bug_reports.jsonl")
REF = "/home/notificationsforsteven/congruencey-harness"        # reference only (read-only)

# ABS_PATH, computed from the boot dir's parent (= checkouts/current). No hard path.
RELOCATABLE_ABS = 'define("ABS_PATH", str_replace(chr(92), "/", dirname(__DIR__)) . "/");'
OLD_ABS_LINE = 'define("ABS_PATH", "/home/notificationsforsteven/congruency/");'

ROUTER = r'''<?php
/* New-tree router for `php -S`. Self-contained bootstrap for checkouts/current,
   pathed entirely off __DIR__ (relocatable). Telemetry stripped — standup only. */
$BOOT = __DIR__;                                        // checkouts/current/boot
define('CONGRUENCY_SQLITE', dirname($BOOT) . '/state/congruency.sqlite');

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');
ini_set('session.save_path', sys_get_temp_dir());
ini_set('sendmail_path', '/bin/true');                 // Orderer::sendEmail() -> no MTA, no-op

require $BOOT . '/shim.php';                            // mysql_* -> PDO/sqlite
require $BOOT . '/Constants_patched.php';               // ABS_PATH (relocatable) + LIB/BIN/ETC/...

foreach ([
    'MYSQL_STORE_DATABASE' => 'store', 'STORE_LOGIN' => 'x', 'STORE_PASSWORD' => 'x',
    'MYSQL_ORDER_DATABASE' => 'order', 'ORDER_LOGIN' => 'x', 'ORDER_PASSWORD' => 'x',
    'MYSQL_FORM_DATABASE'  => 'form',  'FORM_LOGIN'  => 'x', 'FORM_PASSWORD'  => 'x',
    'ETC' => ABS_PATH . 'etc/',
] as $k => $v) { if (!defined($k)) define($k, $v); }

set_include_path($BOOT . PATH_SEPARATOR . get_include_path());  // shadow the app's illegal AutoLoader.php
require(CLASS_LOADER_HEADER);
session_start();

if (isset($_GET['fresh'])) { unset($_SESSION['POM']); }

$LOADER = getClassLoader();                             // PHP 8: SPL autoload, not __autoLoad()
spl_autoload_register(function ($class) use ($LOADER) { $LOADER->loadClassByName($class); });

PersistentObjectManager::getPOM();
include(BIN . "Initialize_POM.php");

Controller::control();

try { PersistentObjectManager::pack($_SESSION['POM']); }
catch (\Throwable $e) { error_log("[POM pack skipped: " . $e->getMessage() . "]"); }
'''


def read_ref(name):
    p = os.path.join(REF, name)
    if not os.path.isfile(p):
        raise FileNotFoundError("reference file missing: %s" % p)
    return open(p).read()


def write(path, content):
    with open(path, "w") as fh:
        fh.write(content)


def repath_serve_override():
    so = os.path.join(SOURCE, "serve_override.php")
    if not os.path.isfile(so):
        return "absent"
    src = open(so).read()
    new = ('<?php\n'
           '/* auto_prepend override, repathed relocatably to THIS checkout. */\n'
           "if (!defined('ABS_PATH')) {\n"
           '    define(\'ABS_PATH\', str_replace(chr(92), "/", __DIR__) . "/");\n'
           '}\n')
    if src != new:
        write(so, new)
        return "repathed"
    return "already"


def main():
    os.makedirs(BOOT, exist_ok=True)

    # 1. shim + neutered autoloader: verbatim (path-agnostic).
    write(os.path.join(BOOT, "shim.php"), read_ref("shim.php"))
    write(os.path.join(BOOT, "AutoLoader.php"), read_ref("AutoLoader.php"))

    # 2. Constants_patched: reference content, ABS_PATH made relocatable.
    consts = read_ref("Constants_patched.php")
    if OLD_ABS_LINE not in consts:
        raise RuntimeError("reference Constants_patched.php ABS_PATH line not found to repath")
    consts = consts.replace(OLD_ABS_LINE, RELOCATABLE_ABS)
    write(os.path.join(BOOT, "Constants_patched.php"), consts)

    # 3. new-tree router.
    write(os.path.join(BOOT, "router.php"), ROUTER)

    # 4. repath the stale serve_override.php too.
    so_state = repath_serve_override()

    entry = {
        "fix": os.path.basename(__file__),
        "target": "checkouts/current/boot/* + serve_override.php",
        "purpose": "boot the CMS from the new path: relocatable ABS_PATH + CONGRUENCY_SQLITE",
        "materialized": ["boot/shim.php", "boot/AutoLoader.php",
                          "boot/Constants_patched.php", "boot/router.php"],
        "serve_override": so_state,
        "recorded": time.strftime("%Y-%m-%dT%H:%M:%S"),
    }
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)
    print(json.dumps({"ok": True, **{k: entry[k] for k in
                     ("materialized", "serve_override")}}, indent=2))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        tb = traceback.format_exc()
        t = {
            "filename": os.path.basename(__file__),
            "function": (traceback.extract_tb(exc.__traceback__)[-1].name
                         if exc.__traceback__ else "?"),
            "time-of-occurance": time.strftime("%Y-%m-%dT%H:%M:%S"),
            "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
            "possible-cause": "%s: %s" % (type(exc).__name__, exc),
            "traceback": tb.strip().splitlines()[-6:],
            "note": "ratchet fix: repath_to_new_tree",
        }
        try:
            with open(BUGS, "a") as fh:
                fh.write(json.dumps(t) + "\n")
        except OSError:
            pass
        print("EXCEPTION — ticket filed", file=sys.stderr)
        raise
