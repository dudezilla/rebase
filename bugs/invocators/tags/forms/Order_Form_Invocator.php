<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
  if(!class_exists("Order_Form_Invocator")){
	class Order_Form_Invocator implements Tag_Interface{

		private $arguments;

		public function __construct($arguments){
			$this->arguments = $arguments;
		}
		
		public function get_document(){
			return OrderForm::launchOrderForm();
		}		
	}		
}
?>
