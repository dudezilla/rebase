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

//Obtain user input for the FCE and IVE of the form.
//?use the formman? YES....
//Build a list of elements to insert... Then insert.

if(!class_exists("ConfigForm_1")){
	class ConfigForm_1{
		private $productKey;
		private $formMan;
		
		public function __construct(){
			$this->setElementKey($_GET['formMaker']);
			if(isset($this->elementKey)){
				$this->formMan = PersistentObjectManager::getData("FORM_MANAGER");
			}else{
				$this->elementKey = NULL;	
			}
		}

		public function control(){
			$formMakerString = NULL;
			if(isset($this->elementKey)){
				return $this->initializeForm() . $this->runForm();	
			}else{
				$formMakerString = "<br>Invalid formKey supplied. <a href='?page=formMakerLink'>Choose another element.</a>";
			}
		}
	
		private function initializeForm(){
			if(!isset($_POST['FORMMAKER'])){
				$elDAO = new FormElementDAO();
				$formEl = $elDAO->obtainRow($this->elementKey);
				if(isset($formEl)){				
					$formArr['formName'] = $formEl->getFormName();
					$formArr['elementString'] = $formEl->getElementString();
					$formArr['implements'] = $formEl->getImplementsClass();
					$formArr['name'] = $formEl->getName();
					$formArr['selection'] = $formEl->getSelectionComment();
					$formArr['required'] = $formEl->getRequired();
					$formArr['order'] = $formEl->getOrder();				
					if(isset($formArr)){
						$this->formMan->setResults("FORMMAKER",$formArr);
						return "<br>Form Element $this->elementKey was found and will be used.";				
					}
				}
				$this->formMan->setResults("FORMMAKER",NULL);
				return "<br>Form Element $this->elementKey was NOT found and can't be used.";				
			}
		}
	
		public function runForm(){
			$formString = $this->formMan->runForm("FORMMAKER");
			$results  = $this->formMan->getResults("FORMMAKER");
			$results['key'] = $this->elementKey;
			PersistentObjectManager::setData("FORMMAKER_RESULT",$results);	
			return $formString;
		}
		
		public function setElementKey($prospect){
			$validated = ValidateFields::validateNumericKey($prospect);
			$this->elementKey = $validated;
			return $this->elementKey;		
		}
	}
}
?>