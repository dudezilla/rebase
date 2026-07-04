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
		
		public function getRequired($bool){	
			$this->required = $bool;
		}
		
		public function isRequired($bool){
			$this->getRequired($bool);	
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