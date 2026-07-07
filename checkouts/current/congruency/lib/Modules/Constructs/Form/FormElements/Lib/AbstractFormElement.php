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

		/* #45: plain-data (de)serialization for JSON-based POM persistence. The $form back-reference
		   is intentionally omitted (rewired via setFormObject() on rebuild) so the graph is acyclic;
		   $validationComments is transient. Subclasses add their own fields via the extra* hooks. */
		public function to_array(){
			return array(
				'__class'       => get_class($this),
				'id'            => $this->id,
				'comment'       => $this->comment,
				'required'      => $this->required,
				'formId'        => $this->formId,
				'elementString' => $this->elementString,
				'order'         => $this->order,
				'tabIndex'      => $this->tabIndex,
				'extra'         => $this->extraToArray(),
			);
		}

		protected function extraToArray(){ return array(); }
		protected function extraFromArray($extra){ }

		public static function from_array($a){
			$cls = isset($a['__class']) ? $a['__class'] : NULL;
			if (!$cls || !class_exists($cls)) { return NULL; }
			$el = new $cls();
			$el->id            = isset($a['id'])            ? $a['id']            : NULL;
			$el->comment       = isset($a['comment'])       ? $a['comment']       : NULL;
			$el->required      = isset($a['required'])      ? $a['required']      : FALSE;
			$el->formId        = isset($a['formId'])        ? $a['formId']        : NULL;
			$el->elementString = isset($a['elementString']) ? $a['elementString'] : NULL;
			$el->order         = isset($a['order'])         ? $a['order']         : NULL;
			$el->tabIndex      = isset($a['tabIndex'])      ? $a['tabIndex']      : NULL;
			$el->extraFromArray(isset($a['extra']) ? $a['extra'] : array());
			return $el;
		}
	}	
}
?>