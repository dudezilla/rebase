<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("TextBox")){
	class TextBox extends AbstractFormElement{

		public function __construct(){
		}
		
		public function getHTML(){
			$result = "<textarea name='" . $this->id ."' ". $this->getTabIndex() ." >" . $this->getInitial() . "</textarea>";
			return $result;
		}		
		
		public function returnValue(){
			$formElementResults = $this->form->getFormData();		
			if(!empty($formElementResults[$this->id])){
				return $formElementResults[$this->id];
			}else{
				return NULL;	
			}
		}
		
		public function getInitial(){
			$value = $this->form->getElementResult($this->getId());
			if(isset($value)){	
				return $value;
			}else{
				return "";	
			}
		}
				
		public function failsExtendedValidation(){
			return FALSE;
		}

	}
}
?>