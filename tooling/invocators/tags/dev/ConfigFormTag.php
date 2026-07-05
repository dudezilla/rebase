<?php
/*
 * ConfigFormTag(productId) — render a product's configuration form.
 *
 * Delegates to the real ConfigForm singleton (same as the legacy
 * Config_Form_Invocator, but with a tag-engine-safe name: the current parser's
 * FUNCTION_NAME is [a-zA-Z]+, so the underscore invocators can't be reached).
 * The form's action (set by its FCE element) chains to the order form.
 */
if (!class_exists("ConfigFormTag")) {
    class ConfigFormTag implements Tag_Interface {
        private $arguments;
        public function __construct($arguments) { $this->arguments = $arguments; }
        public function get_document() {
            try {
                $productId = $this->arguments ? $this->arguments->pop() : '';
                return ConfigForm::launchConfigForm($productId);
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>ConfigFormTag error: "
                     . htmlspecialchars(get_class($e) . ': ' . $e->getMessage()) . "</div>";
            }
        }
    }
}
?>
