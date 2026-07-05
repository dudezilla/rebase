<?php
/* BUG-10  RadioSelect can't parse its options (split() removed) — end-to-end
   The same split() defect as BUG-09, reached the way the form builder actually
   configures a radio element: setElementString() -> setOptions() ->
   FormElementUtils::parseRadioElementString() -> split(). So every form
   containing a radio element fatals when rendered.
   vendor/congruency/lib/Modules/Constructs/Form/FormElements/BasicElements/RadioSelect.php:61 */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    bug_report(
        "Error: Call to undefined function split()",
        function () {
            $radio = new RadioSelect();
            $radio->setElementString("<<Apple>><<Banana>><<Cherry>>");   // how options are configured
        }
    );
}

reproduce();
