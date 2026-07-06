<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("ConfigFormRadioSelect")){
	class ConfigFormRadioSelect extends AbstractFormElement{

		private $options;
		private $isChecked;

		public function __construct(){
			$this->isChecked = FALSE;
		}
		
		public function getHTML(){
			$result = NULL;
			$subElementCount = 1;
			foreach($this->options as $option){
				$result .="<INPUT TYPE='radio' NAME='".$this->id."' VALUE='". $option ."' ". $this->testChecked($option,$subElementCount++).">";
				$result .= ConfigForm::obtainDescription($option) . "\n<br>\n";
			}
			return $result;
		}		
			

		public function returnValue(){
			$formElementResults = $this->form->getFormData();
			if(isset($formElementResults[$this->id])){
			    ConfigForm::enqueue($formElementResults[$this->id],$this->id);
				return ConfigForm::obtainDescription($formElementResults[$this->id]);
			}
		}
		
		public function failsExtendedValidation(){
			return FALSE;
		}
		
		public function getInitial(){
			return $this->returnValue($this->form->getFormData());
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
				ConfigForm::enqueue($subElement,$this->id);
				return "checked='checked' " . $this->getTabIndex();
			}else if(($subElCount == count($this->options))&& !$this->isChecked){
				return $this->getTabIndex();
			}
		}		
 	}
}
?>