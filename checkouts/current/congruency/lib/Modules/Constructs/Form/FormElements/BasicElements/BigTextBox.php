<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("BigTextBox")){
	class BigTextBox extends AbstractFormElement{

		public function __construct(){
		}
		
		public function getHTML(){
			$result = "<textarea name='" . $this->id ."' ". $this->getTabIndex() ." rows='40' cols='120'>" . $this->getInitial() . "</textarea>";
			return $result;
		}		
		
		public function returnValue(){
			$formElementResults = $this->form->getFormData();		
			if(isset($formElementResults[$this->id])){
				return $formElementResults[$this->id];
			}else{
				return "";	
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