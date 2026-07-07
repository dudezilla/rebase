<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* configure.php — the CMS configuration script (replaces Constants_patched.php + config_loader.php).

   The constant VALUES are DATA: boot/constants.default.json (defaults) merged with an optional install
   override (install.json). This script loops over that data and define()s every constant, then computes
   the handful of DERIVED path constants (built from ABS_PATH/SLASH — not expressible as flat data).

   Install override path: $CONGRUENCY_CONFIG env, else <deploy-root>/install.json (deploy-root = the
   parent of boot/). install.json is a flat map { "CONSTANT_NAME": value, ... }; anything it sets wins. */

$__data = json_decode(file_get_contents(__DIR__ . '/constants.default.json'), true);
if (!is_array($__data)) {
    $__data = array();
}
$__cfg = getenv('CONGRUENCY_CONFIG');
if (!$__cfg) {
    $__cfg = dirname(__DIR__) . '/install.json';
}
if (is_file($__cfg)) {
    $__override = json_decode(file_get_contents($__cfg), true);
    if (is_array($__override)) {
        $__data = array_merge($__data, $__override);
    }
}

// one loop over the data -> constants
foreach ($__data as $__name => $__value) {
    if (is_string($__name) && !defined($__name)) {
        define($__name, $__value);
    }
}

// derived path constants (computed from ABS_PATH / SLASH; data above may pre-empt any of them)
if (!defined('SLASH'))               { define('SLASH', '/'); }
if (!defined('PLATFORMROOT'))        { define('PLATFORMROOT', SLASH); }
if (!defined('ABS_PATH'))            { define('ABS_PATH', str_replace(chr(92), '/', dirname(__DIR__)) . '/'); }
if (!defined('CONGRUENCY_SQLITE'))   { define('CONGRUENCY_SQLITE', dirname(rtrim(ABS_PATH, SLASH)) . SLASH . 'state' . SLASH . 'congruency.sqlite'); }  // state is a sibling of the app root (checkouts/current/state)
if (!defined('TAGS_DIR'))            { define('TAGS_DIR', ABS_PATH . 'invocators' . SLASH . 'tags' . SLASH); }
if (!defined('CONTENT_DIR'))         { define('CONTENT_DIR', ABS_PATH . 'content' . SLASH . 'content' . SLASH); }
if (!defined('CLASS_LOADER_DIR'))    { define('CLASS_LOADER_DIR', ABS_PATH . 'lib' . SLASH . 'ClassLoader' . SLASH); }
if (!defined('CLASS_LOADER_HEADER')) { define('CLASS_LOADER_HEADER', ABS_PATH . 'lib' . SLASH . 'ClassLoader' . SLASH . 'ClassLoaderHeader.php'); }
if (!defined('ETC'))                 { define('ETC', ABS_PATH . 'etc' . SLASH); }
if (!defined('LIB'))                 { define('LIB', ABS_PATH . 'lib'); }
if (!defined('BIN'))                 { define('BIN', ABS_PATH . 'bin' . SLASH); }
if (!defined('HARNESS'))             { define('HARNESS', BIN . 'Harness' . SLASH); }
