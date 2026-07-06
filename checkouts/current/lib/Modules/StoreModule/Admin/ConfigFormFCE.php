<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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
				if(!isset($iVE) || !isset($fCE)){
					return NULL;   //a config form needs both its IVE and FCE marker elements
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