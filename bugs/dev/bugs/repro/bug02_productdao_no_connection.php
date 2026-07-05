<?php
/* BUG-02  ProductDAO never opens a database connection
   Its constructor sets $this->table but omits the CreateConnection(...)->open()
   that every sibling DAO has, so the first query dereferences null.
   vendor/congruencey/lib/Modules/StoreModule/Catalog/DAO/ProductDAO.php:25 */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    bug_report(
        "Error: Call to a member function query() on null",
        function () {
            $dao = new ProductDAO();
            $dao->obtainAllRows();   // -> AbstractDAO::query() -> null->query()
        }
    );
}

reproduce();
