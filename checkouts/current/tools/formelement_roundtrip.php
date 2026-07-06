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
require $B . 'BasicElements/BigTextBox.php';
require $B . 'BasicElements/DbSelect.php';
require $B . 'BasicElements/Checkbox.php';
require $B . 'BasicElements/FormConfigElement.php';
require $B . '../FormLogic/StandardForm.php';
require $B . '../FormInterface/FormManager.php';

// DbSelect is DB-backed; point CONGRUENCY_SQLITE at the install DB so its options load
$__cfg = dirname(__DIR__) . '/install.json';
if (is_file($__cfg)) {
    $__j = json_decode(file_get_contents($__cfg), true);
    if (!empty($__j['CONGRUENCY_SQLITE'])) { define('CONGRUENCY_SQLITE', $__j['CONGRUENCY_SQLITE']); }
}

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

// --- BigTextBox (no element-specific state; base fields only) ---
$bt = new BigTextBox();
$bt->setId('body'); $bt->setSelectionComment('Body:'); $bt->setOrder(4); $bt->setTabIndex(4);
$b = roundtrip($bt);
check('BigTextBox round-trips (class/id/order via base)',
      $b instanceof BigTextBox && $b->getId() === 'body' && $b->getCompareValue() == 4);

// --- DbSelect (DB-backed <select>; options re-derived from elementString, not serialized) ---
$ds = new DbSelect();
$ds->setId('pageId'); $ds->setSelectionComment('Page:'); $ds->setOrder(5); $ds->setTabIndex(5);
$ds->setElementString('pages');
$b = roundtrip($ds);
check('DbSelect round-trips (class/id; options re-derived from elementString)',
      $b instanceof DbSelect && $b->getId() === 'pageId' && $b->getOptions() == $ds->getOptions()
      && count($ds->getOptions()) > 0);

// --- Checkbox (no element-specific state; base fields only) ---
$cb = new Checkbox();
$cb->setId('urgent'); $cb->setSelectionComment('Urgent?'); $cb->setOrder(6); $cb->setTabIndex(6);
$b = roundtrip($cb);
check('Checkbox round-trips (class/id/order via base)',
      $b instanceof Checkbox && $b->getId() === 'urgent' && $b->getCompareValue() == 6);

// --- FormConfigElement (FCE: action/oncomplete parsed from elementString on demand; base fields only) ---
$fce = new FormConfigElement();
$fce->setId('__fce');
$fce->setElementString("<action='TicketLogger'><oncomplete='Thanks'><incomplete='Fill it in'>");
$b = roundtrip($fce);
check('FormConfigElement round-trips (elementString -> action/oncomplete re-parse)',
      $b instanceof FormConfigElement
      && FormConfigElement::parseActionTag($b->getElementString()) === 'TicketLogger'
      && FormConfigElement::parseOnCompleteTag($b->getElementString()) === 'Thanks');

// --- StandardForm (composes elements; from_array rebuilds + rewires the circular back-refs) ---
$f = new StandardForm('demoForm');
$r2 = new RadioSelect(); $r2->setId('type'); $r2->setElementString('<<build>><<bug>>'); $r2->setOrder(1);
$t2 = new TextBox(); $t2->setId('description'); $t2->setOrder(2);
$f->addElement($r2); $f->addElement($t2);
$f->setElementResult('type', 'bug');
$f->setElementResult('description', 'the thing broke');
$fb = StandardForm::from_array(json_decode(json_encode($f->to_array()), true));
$rpEls = new ReflectionProperty('StandardForm', 'formElements'); $rpEls->setAccessible(true);
$rebuilt = $rpEls->getValue($fb);
$rpForm = new ReflectionProperty('AbstractFormElement', 'form'); $rpForm->setAccessible(true);
check('StandardForm round-trips (results survive + 2 elements + back-refs rewired to new form)',
      $fb instanceof StandardForm
      && $fb->getElementResult('type') === 'bug'
      && $fb->getElementResult('description') === 'the thing broke'
      && $fb->getResults() == array('type' => 'bug', 'description' => 'the thing broke')
      && $fb->getId() === 'demoForm'
      && count($rebuilt) === 2
      && ($rebuilt['type'] ?? null) instanceof RadioSelect
      && ($rebuilt['description'] ?? null) instanceof TextBox
      && $rebuilt['type']->getId() === 'type'
      && $rpForm->getValue($rebuilt['type']) === $fb);

// --- FormManager (id-keyed StandardForms; the POM's top-level form value) ---
$fm = new FormManager();
$innerForm = new StandardForm('demoForm');
$ir = new RadioSelect(); $ir->setId('type'); $ir->setElementString('<<build>><<bug>>');
$innerForm->addElement($ir);
$innerForm->setElementResult('type', 'bug');
$rpFA = new ReflectionProperty('FormManager', 'formsArray'); $rpFA->setAccessible(true);
$rpFA->setValue($fm, array('demoForm' => $innerForm));
$fmb = FormManager::from_array(json_decode(json_encode($fm->to_array()), true));
check('FormManager round-trips (getResults survives through the whole form graph)',
      $fmb instanceof FormManager && $fmb->getResults('demoForm') == array('type' => 'bug'));

echo "formelement round-trip: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
