<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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

		protected function extraToArray(){                       // #45
			return array('options' => $this->options, 'isChecked' => $this->isChecked);
		}
		protected function extraFromArray($extra){               // #45
			$this->options   = isset($extra['options'])   ? $extra['options']   : NULL;
			$this->isChecked = isset($extra['isChecked']) ? $extra['isChecked'] : NULL;
		}
 	}

}
?>