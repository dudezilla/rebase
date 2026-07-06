<?php
/* New-tree router for `php -S`. Self-contained bootstrap for checkouts/current,
   pathed entirely off __DIR__ (relocatable). Telemetry stripped — standup only. */
$BOOT = __DIR__;                                        // checkouts/current/boot

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');
ini_set('session.save_path', sys_get_temp_dir());
ini_set('sendmail_path', '/bin/true');                 // Orderer::sendEmail() -> no MTA, no-op

require $BOOT . '/shim.php';                            // mysql_* -> PDO/sqlite
require $BOOT . '/configure.php';                       // install.json + defaults -> ALL constants


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
