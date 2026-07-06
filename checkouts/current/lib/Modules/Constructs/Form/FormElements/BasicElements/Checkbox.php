<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * Checkbox — a boolean form element (ticket #44: more form elements). Renders an
 * <input type=checkbox>; returnValue() is 'on' when checked, else null. Usually
 * optional (required=0); failsExtendedValidation never fails.
 */
if (!class_exists("Checkbox")) {
    class Checkbox extends AbstractFormElement {

        public function __construct() {}

        public function getHTML() {
            $checked = ($this->getInitial() !== null) ? " checked='checked'" : "";
            return "<input type='checkbox' name='" . $this->id . "' value='on'" . $checked . " " . $this->getTabIndex() . ">";
        }

        public function returnValue() {
            $d = $this->form->getFormData();
            return (isset($d[$this->id]) && $d[$this->id] !== '') ? $d[$this->id] : null;
        }

        public function getInitial() { return $this->returnValue(); }

        public function failsExtendedValidation() { return FALSE; }
    }
}
?>
