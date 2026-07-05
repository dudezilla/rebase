<?php
/* BUG-03  OrderDAO::updateRow calls a method that does not exist
   updateRow() calls $this->insert() (no such method anywhere in the hierarchy),
   and $this->delete($rowData['key']) passes a bare key where a WHERE-clause
   string is expected, producing malformed "DELETE FROM orders 1".
   vendor/congruencey/lib/Modules/StoreModule/Order/DAO/OrderDAO.php:65 */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    echo "method_exists(OrderDAO,'insert')? " . (method_exists('OrderDAO', 'insert') ? 'yes' : 'no') . "\n";
    bug_report(
        "Error: Call to undefined method OrderDAO::insert()",
        function () {
            $dao = new OrderDAO();
            $dao->updateRow(['key' => 1, 'clientName' => 'x']);
        }
    );
}

reproduce();
