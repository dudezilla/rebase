<?php
/* BUG-01  SQL injection in CatalogDAO::select_products_by_category
   The method validates the key into $itemKey but then guards on isset($key)
   and interpolates the RAW $key into SQL, so the validation is dead code.
   vendor/congruencey/lib/Modules/StoreModule/Catalog/DAO/CatalogDAO.php:58 */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    $dao   = new CatalogDAO();
    $legit = $dao->select_products_by_category('5');           // category 5 -> 2 rows
    $evil  = $dao->select_products_by_category('0 OR 1=1');    // should be rejected by validation

    echo "legit key '5'      -> " . count($legit) . " row(s)\n";
    echo "inject '0 OR 1=1'  -> " . count($evil) . " row(s)\n";
    if (count($evil) > count($legit)) {
        echo "INJECTION CONFIRMED: the validated value is discarded; raw input reaches SQL\n";
    } else {
        echo "not injectable in this build\n";
    }
}

reproduce();
