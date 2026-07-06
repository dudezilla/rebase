<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/

/***************************
Goals:
	
FormManager.
The FormManager is a module level variable, which consolidates all Form objects.
	
	+To provide a layer of abstraction between the module and the Form system.
	Reduce coupling between the Form Module and any module that requires it.

	+Caches forms to reduce the number of database calls.
	
**********************/
if(!class_exists("FormManager")){
	define("FORM_LOAD_FAILURE","<!--FAILED TO LOAD FORM-->");
	class FormManager{
		private $formsArray;
		
		public function __construct(){
		}

		public function runForm($formId){
			$formString = NULL;
			$form = $this->loadForm($formId);
			if(isset($form)){
				$formString = $form->__toString();
			}else{
				$formString = FORM_LOAD_FAILURE;	
			}
			return $formString;
		}
					
		private function loadForm($formId){
			$form = $this->getCachedForm($formId);
			if(!isset($form)){
				$form = $this->lookupForm($formId);
			}
			return $form;
		}
		
		private function lookupForm($formId){
			$form = new StandardForm($formId);
			$this->produceForm($formId,$form);
			if(isset($form)){
				$this->formsArray[$formId] = $form;
				return $this->formsArray[$formId];
			}	
		}
		
		public function getCachedForm($formId){
			if(isset($this->formsArray[$formId])){
				$this->testRecalled($this->formsArray[$formId]);
				return $this->formsArray[$formId];
			}
		}
		
		public function getResults($formId){
			if(isset($this->formsArray[$formId])){
				return $this->formsArray[$formId]->getResults();	
			}else{
				return NULL;
			}			
		}
		
		public function setResults($formId,$formResults){
			if(!isset($this->formsArray[$formId])){
				$this->lookupForm($formId);
			}
			if(isset($this->formsArray[$formId])){
				$this->formsArray[$formId]->setResults($formResults);
			}
		}
		
		/*
			You can link away from an incomplete form...
			
			If you do, the partial results (often invalid) are stored in the 
			form. In effect anytime you return to the form you will encounter
			the incomplete data. 
			
			This is OK for forms that hold user data, maybe config forms, 
			but it's not ok for the forms that are used for Admin.
			 
		*/
		private function testRecalled($form){
			$formData = $form->getFormData();
			if(!isset($formData[$form->getId()])){
				$form->reset();
			}	
		}
		

		/*
			There is no FormDAO Class...
		
			It's not essential.
		
		*/
		private function produceForm($formName,&$form){
			if(isset($formName)){
				$formElementDAO = new FormElementDAO();
				$beansArray = $formElementDAO->obtainNRows($formName);
				if(!empty($beansArray)){
					foreach($beansArray as $element){
						$form->addElement($element->produceElement());
					}
				}else{
					$form = NULL;
				}
			}
		}

		/* #45: JSON (de)serialization for POM persistence — formsArray is an id-keyed map of StandardForm. */
		public function to_array(){
			$forms = array();
			if(!empty($this->formsArray)){
				foreach($this->formsArray as $id=>$form){
					$forms[$id] = $form->to_array();
				}
			}
			return array('__class'=>'FormManager', 'formsArray'=>$forms);
		}

		public static function from_array($a){
			$fm = new FormManager();
			$fm->formsArray = array();
			if(!empty($a['formsArray'])){
				foreach($a['formsArray'] as $id=>$fData){
					$fm->formsArray[$id] = StandardForm::from_array($fData);
				}
			}
			return $fm;
		}
	}		
}
?>