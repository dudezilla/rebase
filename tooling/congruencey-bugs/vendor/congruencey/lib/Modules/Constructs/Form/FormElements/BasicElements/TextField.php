<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("TextField")){
	class TextField extends AbstractFormElement{

		public function __construct(){
		}
		
		public function getHTML(){
			$result = "<input type='text' name='" . $this->id . "'" .  $this->getInitial() ." ". $this->getTabIndex() ." >\n<br>\n";
			return $result;
		}		
		
		public function returnValue(){
			$formElementResults = $this->form->getFormData();
			if(!empty($formElementResults[$this->id])){
				return $formElementResults[$this->id];
			}
		}
		
		public function getInitial(){
			$prefix = "value='";
			$postfix = "'";
			$value = $this->form->getElementResult($this->getId());
			if(isset($value)){		
				return $prefix.$value.$postfix;	
			}else{
				return NULL;	
			}
		}
		
		public function failsExtendedValidation(){
			return FALSE;
		}

				
	}
}
?>