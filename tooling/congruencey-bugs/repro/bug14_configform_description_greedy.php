<?php
/* BUG-14  ConfigForm::obtainDescription greedy .* over-captures
   /##\s?[Dd]escription\s?=.*##/ uses a greedy .*, so with more than one element
   present it matches through to the LAST ##, swallowing neighbouring elements'
   content (calculateEstimate() concatenates queued option strings and re-parses).
   vendor/congruency/lib/Modules/StoreModule/Order/OrderSystem/ConfigForm.php:76 */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    $subject = "## Description=First ## ## Description=Second ##";
    $got = ConfigForm::obtainDescription($subject);
    echo "input: $subject\n";
    echo "obtainDescription = " . var_export($got, true) . "\n";
    if (strpos((string)$got, 'Second') !== false) {
        echo "GREEDY OVER-CAPTURE: first description swallowed the second element\n";
    } else {
        echo "isolated correctly (not reproduced)\n";
    }
}

reproduce();
