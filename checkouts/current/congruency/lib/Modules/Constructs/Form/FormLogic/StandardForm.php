<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("StandardForm")){
	class StandardForm{
		private $id;			//id of Form. 
		private $methodType;	//HTML value GET or POST
		private $action;		//HTML value action='...'
		private $classes;		//array: $classes[FormElementClass] == greater than zero if class is used.
		private $elementResults;	//recorded results for the form elements.
		private $formElements;	//array of form elements. An array of objects.
		private $onComplete;	//When the form is complete this message is displayed.
		private $incomplete;	//When the form is incomplete this message is displayed.
		private $valid;			//This message precedes any valid elements.
		private $isComplete;	//complete flag.
		private $isVerified;
		private $firstDisplay;
		private $sorter;

		private function validateForm(){
			if(!empty($this->formElements)){
				foreach($this->formElements as $element){
					if($element->failsValidation()){
						return FALSE;
					}	
				}
			}
			return TRUE;
		}
		
		private function pullResults(){
			if(!empty($this->formElements)){
				foreach($this->formElements as $element){
					$this->setElementResult($element->getId(),$element->returnValue());
				}
			}			
		}
		

		//Getters and setters.
		public function setValid($string){
			$this->valid = $string;	
		}
		
		public function getValid(){
			return $this->valid;	
		}
		
		public function setIncomplete($message){
			if(isset($message)){
			   $this->incomplete= $message;
			}
		}
		
		public function getIncomplete(){  
			return $this->incomplete;	
		}
		
		public function setOnComplete($onComplete){
			$this->onComplete=$onComplete;	
		}
				
		public function getOnComplete(){
			return $this->onComplete;	
		}
		
		public function formConfigElement($FCE){
			if(isset($this->formElements[$FCE->getId()])){
				unset($this->formElements[$FCE->getId()]);
			}
		}
						
		public function addElement($formElement){
			$this->formElements[$formElement->getId()] = $formElement;
			$formElement->setFormObject($this);
			$this->classes[get_class($formElement)] = NULL;
		}		
		
		public function getId(){
			return $this->id;
		}
		
		public function setID($ID){
			if(isset($ID)){
				$this->id = $ID;
			}else{
				$this->id = 'DEFAULT_FORM';
			}			
		}
		
		public function obtainAction(){
			$this->isComplete = $this->validateForm();
			if(!$this->firstDisplay && $this->isComplete){
				return $this->action;
			}else{
				return NULL;	
			}
		}
		
		public function setAction($arg){
			$this->action = $arg;
		}

		public function setStyle($arg){
			$this->style=$arg;
		}
		
		public function getStyle(){
			return $this->style;
		}
		
		public function usePostMethod(){
			$this->methodType='POST';
		}
		
		public function getFormData(){
			if($this->methodType == "GET"){
				return $_GET;
			}else{
				return $_POST;
			}
		}
		
		public function getMethodType(){
			return $this->methodType;		
		}
		
		//get the results of a form. A value for each element.
		public function getResults(){
			return $this->elementResults;
		}

		public function setResults($resultsArray){
			$this->elementResults = $resultsArray;
		}
		
		//get the value for a particular element.
		public function getElementResult($id){
			if(isset($this->elementResults[$id])){
				return $this->elementResults[$id];
			}else{
				return NULL;
			}	
		}
		
		//set the value for a particular element.
		public function setElementResult($id,$value){
			$this->elementResults[$id] = $value;
		}
		
		/* #45: __wakeup removed — the POM no longer unserializes StandardForm (data is JSON via to_array/from_array);
		   the app autoloader loads element classes on demand. */
		
		public function __construct($ID){
			$this->usePostMethod();
			$this->setID($ID);
			$this->setResults(array());
			$this->firstDisplay = TRUE;
			$this->sorter = NULL;
			$this->incomplete= NULL;
		}
		
		private function getSorter(){
			if(!isset($this->sorter)){
				$this->sorter = new SortIterator();
				$this->sorter->setUnsorted($this->formElements);
			}
			$this->sorter->reset();
			return $this->sorter;	
		}
		
		private function finalElementStrings(){
			for($elementString=NULL,$sorter=$this->getSorter(),$element=$sorter->current();!$sorter->finished();$element=$sorter->next()){
				$elementString .= $element->finalString();	
			}
			return $elementString;
		}
			
		private function elementStrings(){
			$completeElements=$completeString=$incompleteString=NULL;
			$completeIndex=0;
			for($tabIndex=1,$sorter=$this->getSorter(),$element=$sorter->current();!$sorter->finished();$element=$sorter->next()){
				if($element->failsValidation()){
					$element->setTabIndex($tabIndex++);
					$incompleteString .= $element->__toString();
				}else{
					$completeElements[$completeIndex++] = $element;
				}	
			}
			$completeString = $this->stringComplete($completeElements,$tabIndex);			
			$completeString = $this->getValid().$completeString;
			return $incompleteString . $completeString;
		}
		
		private function stringComplete($completeElements,$tabIndex){
			$completeString = NULL;
			if(!empty($completeElements)){
				foreach($completeElements as $complete){
					$complete->setTabIndex($tabIndex++);
					$completeString .= $complete->__toString();
				}
			}
			return $completeString;
		}
				
		private function getElementStrings(){
			$elementStrings = NULL;
			if($this->firstDisplay){
				$elementStrings = $this->firstDisplay();
			}else{
				$this->pullResults();
				if($this->isComplete){
					$elementStrings = $this->getOnComplete();
					$elementStrings .= $this->finalElementStrings();
				}else{
					$elementStrings = $this->getIncomplete();
					$elementStrings .= $this->elementStrings();
				}
			}
			return $elementStrings;	
		}
		
		private function firstDisplay(){
			$elementString=NULL;
			for($tabIndex = 1,$sorter=$this->getSorter(),$element=$sorter->current();!$sorter->finished();$element=$sorter->next()){
				$element->setTabIndex($tabIndex++);
				$elementString .= $element->__toString();
			}
			$this->firstDisplay=FALSE;
			return $elementString;
		}
		
		public function __toString(){			
			$formString = "\n<form id='".$this->getId()."' action='" . $this->obtainAction() . "' method='". $this->getMethodType() . "'>\n<br>\n";
			$formString .= $this->getElementStrings();
			$formString .= "\n<br>\n <input type='hidden' name='". $this->getId() ."' value='1'>";
			$formString .= "\n<br>\n <input type='submit' value='Continue'>";
			$formString .= "\n</form>";
			return $formString;
		}

		public function reset(){
			$this->isComplete=FALSE;
			$this->isVerified=FALSE;
			$this->firstDisplay=TRUE;
			$sorter = $this->getSorter();
			$sorter->reset();		
		}

		/* #45: plain-data (de)serialization for JSON-based POM persistence. formElements are stored as
		   an id-keyed map of each element's to_array(); the transient $sorter is skipped (rebuilt lazily).
		   The FCE is already removed from formElements during build, so no config side effects on rebuild. */
		public function to_array(){
			$elements = array();
			if(!empty($this->formElements)){
				foreach($this->formElements as $key=>$el){
					$elements[$key] = $el->to_array();
				}
			}
			return array(
				'__class'        => 'StandardForm',
				'id'             => $this->id,
				'methodType'     => $this->methodType,
				'action'         => $this->action,
				'onComplete'     => $this->onComplete,
				'incomplete'     => $this->incomplete,
				'valid'          => $this->valid,
				'style'          => isset($this->style) ? $this->style : NULL,
				'isComplete'     => $this->isComplete,
				'isVerified'     => $this->isVerified,
				'firstDisplay'   => $this->firstDisplay,
				'classes'        => $this->classes,
				'elementResults' => $this->elementResults,
				'formElements'   => $elements,
			);
		}

		public static function from_array($a){
			$form = (new ReflectionClass('StandardForm'))->newInstanceWithoutConstructor();
			$form->id             = isset($a['id']) ? $a['id'] : NULL;
			$form->methodType     = isset($a['methodType']) ? $a['methodType'] : 'POST';
			$form->action         = isset($a['action']) ? $a['action'] : NULL;
			$form->onComplete     = isset($a['onComplete']) ? $a['onComplete'] : NULL;
			$form->incomplete     = isset($a['incomplete']) ? $a['incomplete'] : NULL;
			$form->valid          = isset($a['valid']) ? $a['valid'] : NULL;
			if(isset($a['style'])){ $form->style = $a['style']; }
			$form->isComplete     = isset($a['isComplete']) ? $a['isComplete'] : NULL;
			$form->isVerified     = isset($a['isVerified']) ? $a['isVerified'] : NULL;
			$form->firstDisplay   = isset($a['firstDisplay']) ? $a['firstDisplay'] : TRUE;
			$form->classes        = isset($a['classes']) ? $a['classes'] : array();
			$form->elementResults = isset($a['elementResults']) ? $a['elementResults'] : array();
			$form->sorter         = NULL;
			$form->formElements   = array();
			if(!empty($a['formElements'])){
				foreach($a['formElements'] as $key=>$elData){
					$el = AbstractFormElement::from_array($elData);
					if($el !== NULL){
						$form->formElements[$key] = $el;
						$el->setFormObject($form);
					}
				}
			}
			return $form;
		}
	}	
}
?>