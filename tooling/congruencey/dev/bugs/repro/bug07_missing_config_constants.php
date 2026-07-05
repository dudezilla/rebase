<?php
/* BUG-07  Store / order / auth modules reference config constants that
   Constants.php never defines (MYSQL_STORE_DATABASE, STORE_LOGIN, ETC, ...).
   On PHP 8 an undefined constant is a fatal Error, so those whole subsystems
   fail at construction. This is why the shipped Install.txt says "does not
   execute". Referenced e.g. at:
     vendor/congruency/.../Catalog/DAO/CatalogDAO.php:39
     vendor/congruency/.../Order/DAO/OrderDAO.php:8
     vendor/congruency/lib/UserAuthentication/UserPrivilegeSet.php:27 (ETC)

   Run with BUG_SKIP_STORE_CONSTS=1 so bootstrap does NOT supply the missing
   constants, reproducing the as-shipped condition. */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    echo "MYSQL_STORE_DATABASE defined? " . (defined('MYSQL_STORE_DATABASE') ? 'yes' : 'no') . "\n";
    echo "ETC defined?                 " . (defined('ETC') ? 'yes' : 'no') . "\n";
    bug_report(
        'Error: Undefined constant "MYSQL_STORE_DATABASE"',
        function () {
            new CatalogDAO();   // constructor references MYSQL_STORE_DATABASE
        }
    );
}

reproduce();
