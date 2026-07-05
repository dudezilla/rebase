<?php
/* Router for `php -S`. Runs the same bootstrap as bin/Execute.php on every
   request, letting the real front controller handle ?page=... from the URL. */
$SCRATCH = __DIR__;
define('CONGRUENCY_SQLITE', $SCRATCH . '/congruency.sqlite');

error_reporting(E_ALL & ~E_DEPRECATED);          // silence PHP 8.2+ dynamic-property noise
ini_set('display_errors', '1');
ini_set('session.save_path', $SCRATCH);
ini_set('sendmail_path', '/bin/true');            // Orderer::sendEmail() calls mail(); no MTA here, so no-op it

// Client-side telemetry: handle beacon ingest (?telemetry=1) and the viewer
// (?telemetry=view) before booting the CMS at all. Exits if it's a telemetry hit.
require $SCRATCH . '/telemetry.php';
telemetry_handle();

require $SCRATCH . '/shim.php';                   // mysql_* + get_magic_quotes_gpc
require $SCRATCH . '/Constants_patched.php';      // ABS_PATH, LIB, BIN, TAGS_DIR, tag regex, ...

// Supply any store/order/auth config constants not already defined (the fixed
// Constants.php now declares these; guard so we never redefine + warn).
foreach ([
    'MYSQL_STORE_DATABASE' => 'store', 'STORE_LOGIN' => 'x', 'STORE_PASSWORD' => 'x',
    'MYSQL_ORDER_DATABASE' => 'order', 'ORDER_LOGIN' => 'x', 'ORDER_PASSWORD' => 'x',
    'MYSQL_FORM_DATABASE'  => 'form',  'FORM_LOGIN'  => 'x', 'FORM_PASSWORD'  => 'x',
    'ETC' => ABS_PATH . 'etc/',
] as $k => $v) { if (!defined($k)) define($k, $v); }

/* --- mirrors bin/Execute.php, but $_GET['page'] comes from the real URL --- */
set_include_path($SCRATCH . PATH_SEPARATOR . get_include_path());  // shadow AutoLoader.php
require(CLASS_LOADER_HEADER);
session_start();

// ?fresh=1 — drop the cached object graph so getPOM() rebuilds it from scratch.
// Initialize_POM then re-scans TAGS_DIR (picking up new tag files) and rebuilds
// the FormManager, all without restarting the server. A dev-only convenience.
if (isset($_GET['fresh'])) {
    unset($_SESSION['POM']);
    if (!headers_sent()) header('X-Congruency-Fresh: rebuilt');
}

$LOADER = getClassLoader();                       // PHP 8: SPL autoload instead of __autoLoad()
spl_autoload_register(function ($class) use ($LOADER) { $LOADER->loadClassByName($class); });

PersistentObjectManager::getPOM();                // unpacks prior state from the session if present
include(BIN . "Initialize_POM.php");

// Render the page, then inject the telemetry-only snippet before </body>.
ob_start();
Controller::control();
$cy_html = ob_get_clean();
echo (strpos($cy_html, '</body>') !== false)
    ? str_replace('</body>', telemetry_script() . "\n</body>", $cy_html)
    : $cy_html . telemetry_script();

try {
    PersistentObjectManager::pack($_SESSION['POM']);
} catch (\Throwable $e) {
    error_log("[POM pack skipped: " . $e->getMessage() . "]");
}
