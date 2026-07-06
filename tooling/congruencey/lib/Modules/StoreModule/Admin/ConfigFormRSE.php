<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/if(!class_exists("ConfigFormRSE")){
	class ConfigFormRSE extends AbstractMaker {
		
		public function __construct(){
			$this->keyVariable = "ElementID";
			$this->formName = "ConfigFormRSE";
			$this->selectionLink = "<a href='?page=configFormMakerOptions'>Config Form Maker Options</a>";
			$this->init();
		}
		
		protected function insertResultKeys($results){
			return $results; 	
		}
		
		protected function useValidator($prospect){
			return ValidateFields::validateNumericKey($prospect);
		}
		
		protected function initFormArray(){
			$result = NULL;
			$formDAO = new FormElementDAO();
			$formEl = $formDAO->obtainRow($this->getKey());
			if(isset($formEl)){				
				$formArr['elementString'] = $formEl->getElementString();
				$formArr['name'] = $formEl->getName();
				$formArr['selection'] = $formEl->getSelectionComment();
				$formArr['required'] = $formEl->getRequired();
				$formArr['order'] = $formEl->getOrder();
				$result = $formArr;
			}
			return $result;
		}
	}	



}
?>