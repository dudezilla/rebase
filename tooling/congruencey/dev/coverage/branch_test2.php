<?php
/* Shows a branch that CANNOT be covered: DataConnection::quote()'s
   get_magic_quotes_gpc() path. That function was removed in PHP 8 (our shim
   returns false), so the branch is dead — 100% is unreachable here. */
require __DIR__ . '/coverage.php';
require dirname(__DIR__) . '/harness/shim.php';   // get_magic_quotes_gpc()=false, mysql_real_escape_string

$src = file_get_contents(dirname(__DIR__, 2) . '/lib/DatabaseDrivers/MySQL/DataConnection.php');
$inst = cov_instrument($src, $branches);
$tmp = __DIR__ . '/_DataConnection.instrumented.php';
file_put_contents($tmp, $inst);
require $tmp;

echo "DataConnection::quote() — branch coverage\n" . str_repeat('-', 44) . "\n";
foreach (['abc', '123', '12.5', "o'brien"] as $v)
    printf("  quote(%-9s) = %s\n", var_export($v, true), var_export(DataConnection::quote($v), true));

$q = array_filter($branches, fn($b) => $b['line'] >= 62 && $b['line'] <= 72);   // just quote()
[$hit, $total] = cov_report($q);
@unlink($tmp);
echo "\nThe line-64 branch (get_magic_quotes_gpc) is UNREACHABLE: the function was\n"
   . "removed in PHP 8 and the shim returns false, so no input can enter it.\n";
// Mechanical assertion: exactly one of quote()'s two branches is coverable.
exit(($hit === 1 && $total === 2) ? 0 : 1);
