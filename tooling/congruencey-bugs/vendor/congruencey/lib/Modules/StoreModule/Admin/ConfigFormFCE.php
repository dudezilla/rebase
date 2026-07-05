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
if(!class_exists("ConfigFormFCE")){
	class ConfigFormFCE extends AbstractMaker {
		public function __construct(){
			$this->keyVariable = "productID";
			$this->formName = "ConfigFormWizardFCE";
			$this->selectionLink = "<a href='?page=configFormMakerLink'>Choose a form to edit</a>";
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
			$form = $formDAO->obtainNRows("ConfigForm-" . $this->key);
			if(isset($form)){				
				$iVE = NULL;
				$fCE = NULL;
				$formElements = array();
				foreach($form as $element){
					if($element->getName() == "FCE"){
						$fCE = $element;	
					}else if($element->getName() == "IVE"){
						$iVE = $element;	
					}else{
						array_push($formElements,$element);
					}
				}
				$iVEString = $iVE->getElementString();
				$formData['basePrice'] = ConfigForm::obtainPrice($iVEString);
				$formData['baseDescription'] = ConfigForm::obtainDescription($iVEString);
				$fCEString = $fCE->getElementString();
			    $formData['action'] = FormConfigElement::parseActionTag($fCEString);
			    $formData['oncomplete'] = FormConfigElement::parseOnCompleteTag($fCEString);
			    $formData['incomplete'] = FormConfigElement::parseIncompleteTag($fCEString);
				$result = $formData;
			}
			return $result;
		}

		public static function execute($productID,$formData){
			$result = NULL;
			$formName = "ConfigForm-" . $productID;
			$action = "<action = '" . $formData['action'] . "'>";
			$onComplete = "<on complete = '" . $formData['oncomplete'] . "'>";
			$incomplete = "<incomplete = '" . $formData['incomplete'] . "'>";
			$initialValue = "<<## price=". $formData['basePrice'] . " ## description=" . $formData['baseDescription'] . "##>>"; 
			$configFCE['name']= "FCE";
			$configFCE['formName']= $formName;
			$configFCE['implements']= "FormConfigElement";
			$configFCE['elementString']= $action . $incomplete . $onComplete;
			$formDAO = new FormElementDAO();
			$formDAO->insertRow($configFCE);
			$result = "Insertion of FCE<hr>\n";
			$result .= DataConnection::mysqlReport();
			$configIVE['name']= "IVE";
			$configIVE['formName']= $formName;
			$configIVE['implements']= "ConfigFormInitialValue";
			$configIVE['elementString']= $initialValue;
			$formDAO->insertRow($configIVE);
			$result = "Insertion of IVE<hr>\n";
			$result .= DataConnection::mysqlReport();
			return $result;
		}		
	}	
}
?>