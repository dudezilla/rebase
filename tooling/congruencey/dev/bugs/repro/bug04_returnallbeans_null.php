<?php
/* BUG-04  returnAllBeans() returns null (not []) on an empty result set
   AbstractDAO::returnAllBeans initialises $beansArray = NULL and only fills it
   inside the loop, so a zero-row query returns null; callers then do
   current($orders) -> TypeError on PHP 8.
   vendor/congruency/lib/DatabaseDrivers/MySQL/AbstractDAO.php:86
   reached via OrderDAO::obtainRow():19 */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    bug_report(
        "TypeError: current(): Argument #1 (\$array) must be of type array, null given",
        function () {
            $dao = new OrderDAO();
            $dao->obtainRow(999);   // no such order -> returnAllBeans() null -> current(null)
        }
    );
}

reproduce();
