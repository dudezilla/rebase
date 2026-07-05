<?php
/*

Why is this necessary...
Das iZ KLAP!!!
REMOVE IT....
*/
if(!class_exists("OrderForm")){
	class OrderForm{
		private static $orderFormObject;
		private $formManager;
		
		private function __construct(){
			$this->formManager = PersistentObjectManager::getData("FORM_MANAGER");
		}
		
		public static function getObjectReference(){
			return self::$orderForm;		
		}

		public static function launchOrderForm(){
			$orderForm = self::instancialize();
			return $orderForm->__toString();
		}

		public function __clone(){
			trigger_error('Attempt to clone singleton type.', E_USER_ERROR);	
		}
		
		private static function instancialize(){
			if(!isset(self::$orderFormObject)){
				self::$orderFormObject = new OrderForm();
			}
			return self::$orderFormObject;
		}

		public function __toString(){
			$formString = $this->formManager->runForm("OrderForm");
			if(!isset($formString)){
				$formString = "<!--Order Form Error-->";	
			}
			return $formString;
		}
	}
}
?>