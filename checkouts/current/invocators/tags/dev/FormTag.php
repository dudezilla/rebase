<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * FormTag — render a stored form by id through the real form pipeline.
 *
 * Usage in document content:  <<<FormTag(contact)>>>
 * Looks up FORM_MANAGER in the POM and calls runForm(), which builds a
 * StandardForm from the `forms` table (FormElementDAO -> FormElementBean ->
 * produceElement) and renders it. The intent was sketched in
 * bin/Harness/FormSystemHarness.php ("FormTag(testForm)"); this implements it.
 *
 * Wrapped in try/catch because congruency's tag engine has no error boundary
 * (see BUG-06) — a form problem should not take the whole page down.
 */
if (!class_exists("FormTag")) {
    class FormTag implements Tag_Interface {

        private $formId;

        public function __construct($arguments) {
            $this->formId = $arguments ? $arguments->pop() : '';
        }

        public function get_document() {
            try {
                $formManager = PersistentObjectManager::getData("FORM_MANAGER");
                if (!isset($formManager)) {
                    return "<!-- FormTag: no FORM_MANAGER in the POM -->";
                }
                $html = $formManager->runForm($this->formId);
                return "<div class='cy-form'>" . $html . "</div>";
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>FormTag error: "
                     . htmlspecialchars(get_class($e) . ': ' . $e->getMessage()) . "</div>";
            }
        }
    }
}
?>
