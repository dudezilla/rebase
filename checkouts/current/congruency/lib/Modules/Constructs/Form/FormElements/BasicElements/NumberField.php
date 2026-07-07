<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * NumberField — a numeric <input type=number> form element (ticket #44: more form elements).
 * Optional min/max bounds are parsed from the elementString ("min=1;max=100" — either bound
 * optional, decimals allowed). failsExtendedValidation rejects non-numeric input and out-of-range
 * values; an empty value is left to the required check. Produced from the forms table via
 * FormElementBean::produceElement (implements='NumberField').
 */
if (!class_exists("NumberField")) {
    class NumberField extends AbstractFormElement {

        private $min = null;
        private $max = null;

        public function __construct() {}

        public function setElementString($elementString) {
            $this->elementString = $elementString;
            $this->min = self::bound($elementString, 'min');
            $this->max = self::bound($elementString, 'max');
        }

        private static function bound($s, $key) {
            if (preg_match('/' . $key . '\s*=\s*(-?\d+(?:\.\d+)?)/i', (string)$s, $m)) {
                return $m[1] + 0;
            }
            return null;
        }

        public function getMin() { return $this->min; }
        public function getMax() { return $this->max; }

        public function getHTML() {
            $cur  = $this->getInitial();
            $attr = "name='" . $this->id . "' " . $this->getTabIndex();
            if ($this->min !== null) { $attr .= " min='" . $this->min . "'"; }
            if ($this->max !== null) { $attr .= " max='" . $this->max . "'"; }
            $val  = ($cur !== null && $cur !== '') ? " value='" . htmlspecialchars((string)$cur, ENT_QUOTES) . "'" : "";
            return "<input type='number' " . $attr . $val . ">";
        }

        public function returnValue() {
            $d = $this->form->getFormData();
            return (isset($d[$this->id]) && $d[$this->id] !== '') ? $d[$this->id] : null;
        }

        public function getInitial() { return $this->returnValue(); }

        public function failsExtendedValidation() {
            $v = $this->returnValue();
            if ($v === null) { return FALSE; }                 // empty -> the required check owns it
            if (!is_numeric($v)) { return TRUE; }              // non-numeric -> invalid
            $n = $v + 0;
            if ($this->min !== null && $n < $this->min) { return TRUE; }
            if ($this->max !== null && $n > $this->max) { return TRUE; }
            return FALSE;
        }

        /* #45: min/max are derived from elementString (re-derivable), so they are not serialized;
           from_array sets elementString on the base directly, so re-derive the bounds on rebuild. */
        protected function extraFromArray($extra) {
            if (isset($this->elementString)) {
                $this->setElementString($this->elementString);
            }
        }
    }
}
?>
