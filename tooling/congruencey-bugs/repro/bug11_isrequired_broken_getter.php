<?php
/* BUG-11  AbstractFormElement::getRequired()/isRequired() are broken getters
   Both are named like getters but each takes a MANDATORY $bool argument,
   assigns it to $this->required, and returns nothing. So:
     - calling isRequired() the way a getter is called throws ArgumentCountError
     - calling getRequired($x) silently OVERWRITES the required flag
   vendor/congruency/lib/Modules/Constructs/Form/FormElements/Lib/AbstractFormElement.php:73 */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    $el = new TextField();
    $el->setRequired(true);

    // Evidence of the mutation side effect: the "getter" flips the field.
    $ref = new ReflectionProperty('AbstractFormElement', 'required');
    $ref->setAccessible(true);
    echo "required after setRequired(true): " . var_export($ref->getValue($el), true) . "\n";
    $el->getRequired(false);   // a getter that mutates
    echo "required after getRequired(false): " . var_export($ref->getValue($el), true)
       . "   <-- the 'getter' overwrote it\n";

    // The headline: calling the getter with no argument is a hard error.
    bug_report(
        "ArgumentCountError: Too few arguments to function AbstractFormElement::isRequired()",
        function () use ($el) {
            $el->isRequired();
        }
    );
}

reproduce();
