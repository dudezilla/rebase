<?php
/* BUG-13  ConfigForm::obtainPrice accepts non-numeric prices
   The decimal group (.[0-9][0-9])? has an UNESCAPED dot, so '.' matches any
   character and a bogus price like 12x99 parses as valid and is later summed
   into the estimate.
   vendor/congruencey/lib/Modules/StoreModule/Order/OrderSystem/ConfigForm.php:68 */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    $got = ConfigForm::obtainPrice("## Price=12x99 ##");
    echo "obtainPrice('## Price=12x99 ##') = " . var_export($got, true) . "\n";
    if ($got === "12x99") {
        echo "PRICE-REGEX BUG: 'x' accepted as a decimal point; garbage price returned\n";
    } else {
        echo "rejected (not reproduced)\n";
    }
}

reproduce();
