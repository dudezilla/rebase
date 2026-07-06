<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * OrderFormTag — render the order form (contact details for a configured item).
 * Delegates to the real OrderForm singleton (tag-safe rename of
 * Order_Form_Invocator). The config form chains here via its action.
 */
if (!class_exists("OrderFormTag")) {
    class OrderFormTag implements Tag_Interface {
        public function __construct($arguments) {}
        public function get_document() {
            try {
                // Show the configuration carried over from the config form, if any.
                $summary = PersistentObjectManager::getData('PRODUCT_DESCRIPTION');
                $summary = isset($summary) ? "<div class='cy-config-summary'>" . $summary . "</div>" : "";
                return $summary . OrderForm::launchOrderForm();
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>OrderFormTag error: "
                     . htmlspecialchars(get_class($e) . ': ' . $e->getMessage()) . "</div>";
            }
        }
    }
}
?>
