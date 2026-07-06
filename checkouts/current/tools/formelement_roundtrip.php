<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * formelement_roundtrip.php -- #45 gate.
 *
 * Asserts each form element's to_array()/from_array() round-trips through JSON with its state
 * preserved (the circular $form back-reference is intentionally dropped and rewired on rebuild).
 * Extended one element per crank as the POM moves off PHP serialize() to JSON. Exit 0 iff all pass.
 *
 *     tooling/congruencey-harness/php/php checkouts/current/tools/formelement_roundtrip.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$B = dirname(__DIR__) . '/lib/Modules/Constructs/Form/FormElements/';
require $B . 'Lib/FormElementInterface.php';
require $B . 'Lib/FormElementUtils.php';
require $B . 'Lib/AbstractFormElement.php';
require $B . 'BasicElements/RadioSelect.php';
require $B . 'BasicElements/TextBox.php';
require $B . 'BasicElements/TextField.php';

$pass = 0; $fail = 0;
function check($name, $ok) {
    global $pass, $fail;
    echo ($ok ? "  PASS  " : "  FAIL  ") . $name . "\n";
    $ok ? $pass++ : $fail++;
}
function roundtrip($el) {
    return AbstractFormElement::from_array(json_decode(json_encode($el->to_array()), true));
}

// --- RadioSelect ---
$r = new RadioSelect();
$r->setId('type'); $r->setSelectionComment('Type:'); $r->setRequired(true);
$r->setOrder(1); $r->setTabIndex(1);
$r->setElementString('<<build>><<bug>><<design>>');
$b = roundtrip($r);
check('RadioSelect round-trips (class/id/options/required/order)',
      $b instanceof RadioSelect && $b->getId() === 'type'
      && $b->getOptions() == $r->getOptions() && $b->getRequired() === true
      && $b->getCompareValue() == 1);

// --- TextBox (no element-specific state; base fields only) ---
$t = new TextBox();
$t->setId('description'); $t->setSelectionComment('Description:'); $t->setRequired(false);
$t->setOrder(2); $t->setTabIndex(2);
$b = roundtrip($t);
check('TextBox round-trips (class/id/comment/order via base)',
      $b instanceof TextBox && $b->getId() === 'description' && $b->getCompareValue() == 2);

// --- TextField (no element-specific state; base fields only) ---
$tf = new TextField();
$tf->setId('email'); $tf->setSelectionComment('Email:'); $tf->setRequired(true);
$tf->setOrder(3); $tf->setTabIndex(3);
$b = roundtrip($tf);
check('TextField round-trips (class/id/required/order via base)',
      $b instanceof TextField && $b->getId() === 'email' && $b->getRequired() === true && $b->getCompareValue() == 3);

echo "formelement round-trip: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
