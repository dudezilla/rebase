<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("ConfigFormInitialValue")){
	class ConfigFormInitialValue extends AbstractFormElement{

		private $options;

		public function __construct(){
		}
		
		
		/* In effect we use the same syntax as we do for a FormConfigRadioSelect Element.  */
		public function getHTML(){
			$result = "";
			if(!empty($this->options)){
				foreach($this->options as $option){
					$result .= ConfigForm::obtainDescription($option) . "\n<br>\n";
				}
			}
			return $result;
		}		
			
		//May wish to return price....		
		public function returnValue(){
			ConfigForm::enqueue($this->options[0],$this->id);
			return NULL;
		}
		
		public function failsExtendedValidation(){
			return FALSE;
		}

		//May wish to return price....		
		public function getInitial(){
			return NULL;
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
 	}
}
?>