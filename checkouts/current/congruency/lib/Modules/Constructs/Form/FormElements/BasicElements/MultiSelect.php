<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * MultiSelect — a multi-value checkbox group form element (ticket #44: more form elements).
 * Its elementString lists options in the same '<<opt>><<opt>>' format as RadioSelect (parsed with
 * FormElementUtils::parseRadioElementString). The field name is rendered as "ID[]" so PHP collects
 * the checked boxes into an array. returnValue() is the array of selected values (or null if none);
 * the option set doubles as the allowlist, so failsExtendedValidation rejects any submitted value
 * not among the options (same discipline as DbSelect). Produced from the forms table via
 * FormElementBean::produceElement (implements='MultiSelect').
 */
if (!class_exists("MultiSelect")) {
    class MultiSelect extends AbstractFormElement {

        private $options = array();

        public function __construct() {}

        public function setElementString($elementString) {
            $this->elementString = $elementString;
            $this->options = FormElementUtils::parseRadioElementString($elementString);
        }

        public function getOptions() { return $this->options; }

        public function getHTML() {
            $selected = (array) $this->getInitial();
            $h = "";
            $subElementCount = 1;
            $count = is_array($this->options) ? count($this->options) : 0;
            foreach ((array) $this->options as $option) {
                $checked = in_array((string) $option, array_map('strval', $selected), true) ? " checked='checked'" : "";
                $tab = ($subElementCount === $count) ? " " . $this->getTabIndex() : "";
                $h .= "<input type='checkbox' name='" . $this->id . "[]' value='"
                    . htmlspecialchars((string) $option, ENT_QUOTES) . "'" . $checked . $tab . ">"
                    . htmlspecialchars((string) $option, ENT_QUOTES) . "\n<br>\n";
                $subElementCount++;
            }
            return $h;
        }

        public function returnValue() {
            $d = $this->form->getFormData();
            $v = isset($d[$this->id]) ? $d[$this->id] : null;
            return (is_array($v) && count($v)) ? array_values($v) : null;
        }

        public function getInitial() { return $this->returnValue(); }

        public function failsExtendedValidation() {
            $sel = $this->returnValue();
            if ($sel === null) { return FALSE; }               // empty -> the required check owns it
            $allow = array_map('strval', (array) $this->options);
            foreach ($sel as $s) {
                if (!in_array((string) $s, $allow, true)) { return TRUE; }   // value not an option
            }
            return FALSE;
        }

        /* #45: $options are derived from elementString (re-derivable), so they are not serialized;
           from_array sets elementString on the base directly, so re-derive the option set on rebuild. */
        protected function extraFromArray($extra) {
            if (isset($this->elementString)) {
                $this->setElementString($this->elementString);
            }
        }
    }
}
?>
