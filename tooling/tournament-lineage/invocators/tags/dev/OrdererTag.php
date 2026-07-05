<?php
/*
 * OrdererTag — finalize an order (step 3 of the wizard).
 *
 * Tag-safe rename of the legacy Orderer_Invocator. Pulls the configured item
 * (PRODUCT_DESCRIPTION) and the submitted OrderForm results out of the POM,
 * logs the order via Orderer::orderFactory (insert + read-back + email), and
 * renders the confirmation. Wrapped in try/catch: the order read-back path can
 * hit BUG-04 (current(null)) if the insert doesn't land a row.
 */
if (!class_exists("OrdererTag")) {
    class OrdererTag implements Tag_Interface {
        public function __construct($arguments) {}
        public function get_document() {
            try {
                $description = PersistentObjectManager::getData('PRODUCT_DESCRIPTION');
                $formManager = PersistentObjectManager::getData('FORM_MANAGER');
                $contactInfo = isset($formManager) ? $formManager->getResults("OrderForm") : null;
                if (!isset($contactInfo)) {
                    return "<p>No order to log — the order form hasn't been submitted this session.</p>";
                }
                $order = Orderer::orderFactory($description, $contactInfo);
                if (isset($order)) {
                    return "<h2 style='font-weight:normal'>Order confirmed</h2>" . $order->table();
                }
                return "<br>Order Error: No order logged";
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>OrdererTag error: "
                     . htmlspecialchars(get_class($e) . ': ' . $e->getMessage()) . "</div>";
            }
        }
    }
}
?>
