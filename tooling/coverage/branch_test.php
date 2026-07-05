<?php
/*
 * Branch-coverage test for ValidateFields (the input whitelist we attacked).
 * Instruments the real source, then exercises every branch and reports measured
 * coverage. Goal: cover every branch point of the three validators.
 */
require __DIR__ . '/coverage.php';
error_reporting(E_ALL & ~E_WARNING);   // the no-match path reads $result[0] (undefined) by design

$SRC = dirname(__DIR__, 2) . '/checkouts/current';   // repo/checkouts/current — relocatable, no hard-coded ~ path
$target = $SRC . '/lib/IOControl/Validators/ValidateFields.php';
$src = file_get_contents($target);
$inst = cov_instrument($src, $branches);
$tmp = __DIR__ . '/_ValidateFields.instrumented.php';
file_put_contents($tmp, $inst);
require $tmp;                            // defines the instrumented ValidateFields

echo "ValidateFields — branch-coverage test\n";
echo str_repeat('-', 52) . "\n";

// Each case is chosen to drive a specific branch (true / false edge of each if).
$cases = [
    // fn,               input,        expected,   branch intent
    ['validatePageKey',  'catalog',    'catalog',  'match & equal -> return'],
    ['validatePageKey',  'ab1',        null,       'match but != (trailing) -> else'],
    ['validatePageKey',  '1',          null,       'no match (no letters) -> else'],
    ['validateNumericKey', '123',      '123',      'match & equal -> return'],
    ['validateNumericKey', '12x',      null,       'match but != -> else'],
    ['validateNumericKey', 'abc',      null,       'no match -> else'],
    ['validateFormKey',  'a-b_1',      'a-b_1',    'match & equal -> return'],
    ['validateFormKey',  'a b',        null,       'match but != (space) -> else'],
    // validateItemKey just delegates to validateNumericKey (no branch of its own)
    ['validateItemKey',  '42',         '42',       'delegate -> numeric true'],
];

$fail = 0;
foreach ($cases as [$fn, $in, $exp, $why]) {
    $got = ValidateFields::$fn($in);
    $ok = ($got === $exp);
    if (!$ok) $fail++;
    printf("  %-18s %-8s -> %-9s %-4s  (%s)\n",
        $fn, var_export($in, true), var_export($got, true), $ok ? 'ok' : 'FAIL', $why);
}

[$hit, $total] = cov_report($branches);
@unlink($tmp);
echo "\n" . ($fail ? "$fail assertion(s) failed\n" : "all assertions passed\n");
echo ($hit === $total && $total > 0 ? "FULL branch coverage of ValidateFields.\n" : "coverage gap remains.\n");
exit($fail || $hit !== $total ? 1 : 0);
