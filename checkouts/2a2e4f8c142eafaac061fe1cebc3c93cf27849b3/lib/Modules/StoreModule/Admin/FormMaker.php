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