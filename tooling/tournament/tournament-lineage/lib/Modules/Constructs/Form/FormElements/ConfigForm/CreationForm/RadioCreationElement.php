<?php
/*
Congruency The web management system.
Copyright (C) 2006 Steven Peterson

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

<<<Contact Info>>>
Steven Peterson,2234 4th Ave NW, Calgary, AB T2N 0N7 Canada. steven.peterson@shaw.ca
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