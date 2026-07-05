<?php
/* BUG-15  ConfigFormFCE::initFormArray null-derefs a missing IVE/FCE element
   It sorts a config form's elements into $fCE / $iVE, then unconditionally calls
   $iVE->getElementString() and $fCE->getElementString(). A config form that has
   elements but no element named 'IVE' leaves $iVE null -> crash editing it.
   vendor/congruencey/lib/Modules/StoreModule/Admin/ConfigFormFCE.php:56

   The FORM DB constants (BUG-12) are defined here so we reach the null-deref
   rather than the undefined-constant fatal. */
define('MYSQL_FORM_DATABASE', 'form'); define('FORM_LOGIN', 'x'); define('FORM_PASSWORD', 'x');
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    $pdo = new PDO('sqlite:' . CONGRUENCY_SQLITE);
    $pdo->exec("CREATE TABLE forms (`key` INTEGER, name TEXT, formName TEXT, elementString TEXT,
                implements TEXT, selection TEXT, required INTEGER, `order` INTEGER)");
    // A ConfigForm-6 element that is neither FCE nor IVE.
    $pdo->exec("INSERT INTO forms VALUES (1,'someOption','ConfigForm-6','<<x>>','RadioSelect','pick',0,1)");
    unset($pdo);

    $_GET['productID'] = '6';
    PersistentObjectManager::setData('FORM_MANAGER', new FormManager());

    bug_report(
        "Error: Call to a member function getElementString() on null",
        function () {
            $fce = new ConfigFormFCE();                      // init() reads $_GET['productID']
            $m = new ReflectionMethod('ConfigFormFCE', 'initFormArray');
            $m->setAccessible(true);
            $m->invoke($fce);                                // -> $iVE->getElementString() on null
        }
    );
}

reproduce();
