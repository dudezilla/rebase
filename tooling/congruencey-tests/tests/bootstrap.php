<?php
/* PHPUnit bootstrap: boot the congruency framework via the durable harness. */
$HARNESS = '/home/notificationsforsteven/congruency-harness';
if (!is_file($HARNESS . '/shim.php')) { fwrite(STDERR, "harness missing at $HARNESS\n"); exit(2); }
define('CONGRUENCY_SQLITE', sys_get_temp_dir() . '/cy_phpunit_' . getmypid() . '.sqlite');
@unlink(CONGRUENCY_SQLITE);
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('session.save_path', sys_get_temp_dir());
require $HARNESS . '/shim.php';
require $HARNESS . '/Constants_patched.php';
set_include_path($HARNESS . PATH_SEPARATOR . get_include_path());
require(CLASS_LOADER_HEADER);
@session_start();
$GLOBALS['__cy_loader'] = getClassLoader();
spl_autoload_register(fn($c) => $GLOBALS['__cy_loader']->loadClassByName($c));
PersistentObjectManager::getPOM();
register_shutdown_function(fn() => @unlink(CONGRUENCY_SQLITE));
