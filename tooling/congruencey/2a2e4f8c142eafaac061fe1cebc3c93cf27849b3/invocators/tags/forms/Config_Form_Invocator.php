<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
  if(!class_exists("Config_Form_Invocator")){
	class Config_Form_Invocator implements Tag_Interface{

		private $arguments;
		private $productID;

		public function __construct($arguments){
			$this->arguments = $arguments;
			$this->prodcutID = $this->arguments->top();
		}
		
		public function get_document(){
			return ConfigForm::launchConfigForm($this->arguments->top());
		}		
	}		
}
?>
