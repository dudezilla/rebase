<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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