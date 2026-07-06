<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
  if(!class_exists("Orderer_Invocator")){
	class Orderer_Invocator implements Tag_Interface{

		private $arguments;

		public function __construct($arguments){
			$this->arguments = $arguments;
		}
		
		public function get_document(){			
			$description = PersistentObjectManager::getData('PRODUCT_DESCRIPTION');
			$contactInfo = PersistentObjectManager::getData('FORM_MANAGER')->getResults("OrderForm");
			$order = Orderer::orderFactory($description,$contactInfo);
			if(isset($order)){
   				return $order->table();
			}else{
   				return "<br>Order Error: No order logged";	
			}
		}		
	}		
}
?>
