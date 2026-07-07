<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("RadioCreationElement")){
	class RadioCreationElement extends AbstractFormElement{
		private $radioInput;
		private $radioElements;
		private $extendedValidation;

		public function __construct(){
			$this->extendedValidation = array();
		}
		
		public function getHTML(){
			$radioArray = $this->parseRadioElementString($this->getInitial());
			return RadioCreation::getRadioString($this->getNumberOfRadios(),$radioArray);	
		}

		public function returnValue(){
			return RadioCreation::getElementString($this->form->getFormData(),$this->getNumberOfRadios());
		}
		
		public function getInitial(){
			return $this->form->getElementResult($this->getId());
		}
		
		public function failsExtendedValidation(){
			foreach($this->extendedValidation as $validation){
				if($validation){
					print("<br>An element failed validation.<br>");
					$this->extendedValidation = array();
					return TRUE;
				}	
			}
			return FALSE;
		}
		
		public function getInitialNumberOfRadios(){
			if(!isset($this->radioElements)){
				$this->parseRadioElementString($this->getInitial());	
			}
			$this->radioInput = count($this->radioElements);
			return $this->radioInput;
		}

		public function getNumberOfRadios(){
			$formData = $this->form->getFormData();
			if(isset($formData['numberOfRadios'])){
				array_push($this->extendedValidation,$this->radioInput != $formData['numberOfRadios']);
				$this->radioInput = $formData['numberOfRadios'];
			}else{
				 $this->getInitialNumberOfRadios();	
			}
			return $this->radioInput;
		}

		private function parseRadioElementString($elementString){
			$this->radioElements = FormElementUtils::parseRadioElementString($elementString);
			return $this->radioElements;
		}
	}
}
?>