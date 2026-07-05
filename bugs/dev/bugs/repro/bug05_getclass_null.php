<?php
/* BUG-05  get_class() on a possibly-null document
   Controller::display() calls setData('WORKING_PAGE', $document) without checking
   that DocumentManager returned a document; setData() then does get_class($data).
   In PHP 5 get_class(null) returned false; on PHP 8 it is a fatal TypeError, so a
   request for a missing page (with no 'invalid' fallback row) hard-crashes.
   vendor/congruencey/lib/PersistanceObjectManager/PersistentObjectManager.php:75
   caller: vendor/congruencey/lib/Controller/Controller.php:52 */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    bug_report(
        "TypeError: get_class(): Argument #1 (\$object) must be of type object, null given",
        function () {
            // exactly what Controller::display() does when the page is not found
            PersistentObjectManager::setData('WORKING_PAGE', null);
        }
    );
}

reproduce();
