<?php
/* BUG-09  FormElementUtils uses split(), removed in PHP 7.0
   The radio-option parser splits the stored element string with split() (a
   POSIX-regex function deleted from PHP in 7.0). Any radio element fatals.
   vendor/congruencey/lib/Modules/Constructs/Form/FormElements/Lib/FormElementUtils.php:47
   Root cause shared with BUG-10 (this is the direct/unit reproduction). */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    bug_report(
        "Error: Call to undefined function split()",
        function () {
            FormElementUtils::validateElementString("<<Apple>><<Banana>>");
        }
    );
}

reproduce();
