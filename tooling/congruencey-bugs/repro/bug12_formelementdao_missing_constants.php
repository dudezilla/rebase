<?php
/* BUG-12  FormElementDAO references FORM DB constants that are never defined
   The constructor uses MYSQL_FORM_DATABASE / FORM_LOGIN / FORM_PASSWORD, none
   of which appear in www/Constants.php. Undefined constants are fatal on PHP 8,
   so the form persistence layer dies at construction. Same family as BUG-07
   (store/order/auth); this is the form-specific instance.
   vendor/congruency/lib/Modules/Constructs/Form/DAO/FormElementDAO.php:30

   NOTE: bootstrap.php supplies the store/order/auth constants (to reach other
   bugs) but deliberately NOT the form ones, so this reproduces as shipped. */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    echo "MYSQL_FORM_DATABASE defined? " . (defined('MYSQL_FORM_DATABASE') ? 'yes' : 'no') . "\n";
    bug_report(
        'Error: Undefined constant "MYSQL_FORM_DATABASE"',
        function () {
            new FormElementDAO();
        }
    );
}

reproduce();
