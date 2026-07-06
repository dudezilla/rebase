<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("AbstractFormElement")){
	abstract class AbstractFormElement implements FormElementInterface{
		protected $id;
		protected $comment;
		protected $required = FALSE;                      
		protected $validationComments = NULL;
		protected $formId;
		protected $elementString;
		protected $form;
		protected $order;
		protected $tabIndex;
		
		public function getCompareValue(){
			return $this->order;	
		}
				
		abstract public function FailsExtendedValidation();
		abstract public function returnValue();
		abstract public function getHTML();
		abstract public function getInitial();
		
		public function __toString(){
			$result = "\n<br>\n" . $this->comment . "<hr>";
			return $result . $this->getHTML();
		}
		
		public function finalString(){
			$result = "\n<br>\n" . $this->comment . "<hr>";
			return $result . $this->form->getElementResult($this->getId());
		}

		public function setFormObject($form){
			$this->form = $form;
		}

		public function setSelectionComment($arg){
			$this->comment = $arg;
		}
		
		public function setId($arg){
			$this->id = $arg;
		}
		
		public function getId(){
			return $this->id;
		}
		
		public function setRequired($bool){	
			$this->required = $bool;
		}
		
		public function getRequired(){
			return $this->required;
		}

		public function isRequired(){
			return $this->required;
		}
				
		public function failsValidation(){
			//changed empty() to isset()
			$ui = $this->returnValue();
			return (($this->required && !isset($ui)) || ($this->failsExtendedValidation()) );
		}
		
/*		public function initialValueFailsValidation($form){
			$ui = $form->getElementResult($this->getId());
			return (($this->required && !isset($ui)) || ($this->failsExtendedValidation()) );
		}			
*/		
		public function getFormId(){
			return $this->formId;
		}
		
		public function setFormId($formId){
			$this->formId = $formId;
		}
				
		public function setElementString($elementString){
			$this->elementString = $elementString;
		}

		public function getElementString(){
			return $this->elementString;
		}
		
		public function getOrder(){
			return $this->order;
		}
		
		public function setOrder($order){
			$this->order = $order;
		}
		
		public function setTabIndex($index){
			$this->tabIndex = $index;
		}
		
		public function getTabIndex(){
			return "tabindex='" . $this->tabIndex . "'";
		}
	}	
}
?>