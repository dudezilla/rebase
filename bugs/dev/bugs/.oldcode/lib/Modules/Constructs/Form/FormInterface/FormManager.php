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
	}		
}
?>