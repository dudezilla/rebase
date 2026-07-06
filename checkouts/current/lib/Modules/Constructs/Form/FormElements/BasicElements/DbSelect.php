<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * DbSelect — a DB-backed <select> form element (a first step on ticket #44:
 * more form elements). Its elementString names a source ('pages' | 'categories')
 * and it renders a dropdown of live rows from CONGRUENCY_SQLITE. Extended
 * validation rejects any submitted value not in the current option set, so the
 * option list doubles as the allowlist.
 */
if (!class_exists("DbSelect")) {
    class DbSelect extends AbstractFormElement {

        private $options = array();   // array of array(value, label)

        public function __construct() {}

        public function setElementString($elementString) {
            $this->elementString = $elementString;
            $this->options = self::load(trim((string)$elementString));
        }

        private static function load($src) {
            $out = array();
            if (!defined('CONGRUENCY_SQLITE')) { return $out; }
            try {
                $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                if ($src === 'pages') {
                    foreach ($db->query("SELECT DocumentID, Title FROM Documents ORDER BY DocumentID") as $r) {
                        $out[] = array($r['DocumentID'], ($r['Title'] !== null && $r['Title'] !== '') ? $r['Title'] : $r['DocumentID']);
                    }
                } elseif ($src === 'categories') {
                    foreach ($db->query("SELECT name FROM Categories ORDER BY name") as $r) {
                        $out[] = array($r['name'], $r['name']);
                    }
                }
            } catch (\Throwable $e) { /* leave options empty; validation will reject */ }
            return $out;
        }

        public function getHTML() {
            $cur = $this->getInitial();
            $h = "<select name='" . $this->id . "' " . $this->getTabIndex() . ">";
            $h .= "<option value=''>-- choose --</option>";
            foreach ($this->options as $o) {
                $v = $o[0]; $l = $o[1];
                $sel = ((string)$v === (string)$cur) ? " selected" : "";
                // labels may already hold HTML entities (e.g. Titles with &middot;) — decode then re-escape once
                $label = htmlspecialchars(html_entity_decode($l, ENT_QUOTES), ENT_QUOTES);
                $h .= "<option value='" . htmlspecialchars($v, ENT_QUOTES) . "'$sel>" . $label . "</option>";
            }
            return $h . "</select>";
        }

        public function returnValue() {
            $d = $this->form->getFormData();
            return (isset($d[$this->id]) && $d[$this->id] !== '') ? $d[$this->id] : null;
        }

        public function getInitial() { return $this->returnValue(); }

        public function failsExtendedValidation() {
            $v = $this->returnValue();
            if ($v === null) { return FALSE; }          // empty handled by the required check
            foreach ($this->options as $o) { if ((string)$o[0] === (string)$v) { return FALSE; } }
            return TRUE;                                 // value not among live options -> invalid
        }
    }
}
?>
