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
if(!class_exists("RadioSelect")){
	class RadioSelect extends AbstractFormElement{

		private $options;
		private $isChecked;

		public function __construct(){
		}
		
		public function getHTML(){
			$result = NULL;
			$subElementCount = 1;
			foreach($this->options as $option){
				$result .="<INPUT TYPE='radio' NAME='".$this->id."' VALUE='". $option ."' ". $this->testChecked($option,$subElementCount++).">";
				$result .= $option . "\n<br>\n";
			}
			return $result;
		}		
			
		public function returnValue(){
			$formElementResults = $this->form->getFormData();
			if(!empty($formElementResults[$this->id])){
				return $formElementResults[$this->id];
			}
		}
		
		public function failsExtendedValidation(){
			return FALSE;
		}
		
		public function getInitial(){
			return $this->returnValue();
		}
			
		public function getOptions(){
			return $this->options;
		}
		
		public function setOptions($elementString){
			$this->options = FormElementUtils::parseRadioElementString($elementString);
		}
		
		public function setElementString($elementString){
			$this->setOptions($elementString);	
		}
		
		private function testChecked($subElement,$subElCount){
			if($subElement == $this->getInitial()){
				$this->isChecked = TRUE;
				return "checked='checked' " . $this->getTabIndex();
			}else if(($subElCount == count($this->options))&& !$this->isChecked){
				return $this->getTabIndex();
			}
		}		
 	}

}
?>