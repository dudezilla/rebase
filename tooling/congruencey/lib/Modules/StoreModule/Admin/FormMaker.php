<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("FormMaker")){
	class FormMaker extends AbstractMaker{
	
		public function __construct(){
			$this->keyVariable = "formMaker";
			$this->formName = "FORMMAKER";
			$this->selectionLink = "<a href='?page=formMakerLink'>Choose another element.</a>";
			$this->init();
		}
		
		protected function insertResultKeys($results){
			$results['key'] = $this->key;
			return $results;	
		}
		
		protected function useValidator($prospect){
			return ValidateFields::validateNumericKey($prospect);
		}
		
		
		//Initizialize the config forms array: the default values for the config-form are set.
		//In this case, the form is used to configure other form elements, and the defualt values are any existing 
		//values entered for an element with this id.
		protected function initFormArray(){
			$result = NULL;
			$elDAO = new FormElementDAO();
			$formEl = $elDAO->obtainRow($this->key);
			if(isset($formEl)){				
				$formArr['formName'] = $formEl->getFormName();
				$formArr['elementString'] = $formEl->getElementString();
				$formArr['implements'] = $formEl->getImplementsClass();
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