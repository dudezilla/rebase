<?php
/*
 * bootstrap.php — boots the congruencey framework (submodule under vendor/)
 * on modern PHP so each bug's reproduce() can drive real 2006 code.
 *
 * Concessions required just to load the code (documented as BUG-07 / BUG-09):
 *   - a mysql_* -> SQLite shim (src/shim.php); ext/mysql was removed in PHP 7
 *   - get_magic_quotes_gpc() stub (same file); removed in PHP 8
 *   - a neutralized AutoLoader.php (PHP 8 forbids declaring __autoLoad())
 *   - the store/order/auth config constants the original Constants.php omits
 *
 * Env knobs:
 *   CONGRUENCEY_PATH      path to the code under test (default: vendor/congruencey)
 *   BUG_SKIP_STORE_CONSTS if set, do NOT define the omitted constants
 *                         (used by BUG-07 to reproduce the "undefined constant" fatal)
 */

define('BUGREPO_ROOT', dirname(__DIR__));

$congruencey = getenv('CONGRUENCEY_PATH');
if ($congruencey === false || $congruencey === '') {
    $congruencey = BUGREPO_ROOT . '/.oldcode';
}
$congruencey = rtrim($congruencey, '/');
if (!is_file($congruencey . '/www/Constants.php')) {
    fwrite(STDERR, "Cannot find congruencey at: $congruencey\n"
        . "Run `git submodule update --init`, or set CONGRUENCEY_PATH.\n");
    exit(2);
}

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

// Fresh throwaway database per process.
$fixtures = BUGREPO_ROOT . '/.fixtures';
@mkdir($fixtures, 0777, true);
define('CONGRUENCY_SQLITE', $fixtures . '/fixture-' . getmypid() . '.sqlite');
@unlink(CONGRUENCY_SQLITE);
ini_set('session.save_path', $fixtures);

// Point ABS_PATH at the submodule BEFORE including the original Constants.php.
// Constants.php re-define()s ABS_PATH to a hardcoded /web/web/ path; because we
// defined it first, that later define() is ignored (PHP keeps the first value),
// and every derived path constant (LIB, BIN, TAGS_DIR, CLASS_LOADER_HEADER...)
// is computed from OUR value. Everything else in Constants.php is used verbatim.
define('ABS_PATH', $congruencey . '/');

// The config constants the store/order/auth modules reference but Constants.php
// never defines. Skipping them is exactly BUG-07.
if (getenv('BUG_SKIP_STORE_CONSTS') === false) {
    define('MYSQL_STORE_DATABASE', 'store'); define('STORE_LOGIN', 'x'); define('STORE_PASSWORD', 'x');
    define('MYSQL_ORDER_DATABASE', 'order'); define('ORDER_LOGIN', 'x'); define('ORDER_PASSWORD', 'x');
    define('ETC', ABS_PATH . 'etc/');
}

require __DIR__ . '/shim.php';

// Include the real Constants.php (its ABS_PATH redefine warns-and-is-ignored).
$level = error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
require $congruencey . '/www/Constants.php';
error_reporting($level);

// Boot the class loader; shadow AutoLoader.php with our stub via include_path.
set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
require CLASS_LOADER_HEADER;
@session_start();

$GLOBALS['__congruencey_loader'] = getClassLoader();
spl_autoload_register(function ($class) {
    $GLOBALS['__congruencey_loader']->loadClassByName($class);
});
PersistentObjectManager::getPOM();

// ---- Seed a small fixture the probes read from. ----
$pdo = new PDO('sqlite:' . CONGRUENCY_SQLITE);
$pdo->exec("CREATE TABLE Products (`key` INTEGER, category INTEGER, name TEXT, description TEXT, page TEXT, picture TEXT)");
$pdo->exec("INSERT INTO Products VALUES (1,5,'Widget','a','',''),(2,5,'Gadget','b','',''),(3,9,'Secret','c','','')");
$pdo->exec("CREATE TABLE orders (orderNumber INTEGER, unixKey INTEGER, clientName TEXT, itemDescription TEXT, clientPhone TEXT, comment TEXT, clientEmail TEXT, date TEXT)");
$pdo->exec("CREATE TABLE Document_Templates (TemplateID INTEGER PRIMARY KEY, Content TEXT)");
$pdo->exec("CREATE TABLE Documents (DocumentID TEXT PRIMARY KEY, TemplateID INTEGER, Title TEXT, Description TEXT, ContentID INTEGER)");
$pdo->exec("INSERT INTO Document_Templates VALUES (1,'<html><<<TitleTag>>></html>')");
$pdo->exec("INSERT INTO Documents VALUES ('catalog',1,'Congruency Lives','x',1)");
unset($pdo);

// Clean up stray fixture files on exit so the repo stays tidy.
register_shutdown_function(function () { @unlink(CONGRUENCY_SQLITE); });

/** Uniform report helper for reproduce() functions. */
function bug_report(string $expected, callable $trigger): void {
    echo "expected: $expected\n";
    try {
        $trigger();
        echo "observed: (no error thrown)\n";
        echo "NOT REPRODUCED\n";
    } catch (\Throwable $e) {
        echo "observed: " . get_class($e) . ": " . $e->getMessage() . "\n";
        echo "  at " . str_replace(BUGREPO_ROOT . '/vendor/congruencey/', '', $e->getFile())
           . ":" . $e->getLine() . "\n";
        echo "REPRODUCED\n";
    }
}
