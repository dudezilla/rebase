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
if(!class_exists("FormElementBean")){
	class FormElementBean{
		private $name;
		private $elementString;
		private $handle;
		private $implementsClass;
		private	$formName;
		private $selectionComment;
		private $required;
		private $order;
		
		
		public static function sql_assoc_array($formEl){	
			$bean = new FormElementBean();
			$bean->setName($formEl['name']);
			$bean->setFormName($formEl['formName']);
			$bean->setElementString($formEl['elementString']);
			$bean->setImplementsClass($formEl['implements']);
			$bean->setHandle($formEl['key']);
			$bean->setSelectionComment($formEl['selection']);
			$bean->setRequired($formEl['required']);
			$bean->setOrder($formEl['order']);
			return $bean;
		}
		
		
		public function setOrder($arg){
			$this->order = $arg;	
		}
		
		public function getOrder(){
			return $this->order;	
		}
		
		public function setRequired($integer){
			if($integer == 0){
				$this->required = FALSE;
			}else{
				$this->required = TRUE;
			}
		}
		
		public function getRequired(){
			return $this->required;	
		}

		public function FormElementBean(){
		}

		public function setSelectionComment($arg){
			$this->selectionComment = $arg;
		}

		public function setResultComment($arg){
			$this->resultComment = $arg;
		}
		
		public function getSelectionComment(){
			return $this->selectionComment;
		}
		
		public function getResultComment(){
			return $this->resultComment;
		}				
		
		public function parseElement(){
			return $this->parseElementString();
		}

		public function setHandle($arg){
			$this->handle = $arg;
		}
		
		public function setName($arg){
			$this->name = $arg;
		}
		
		public function setElementString($arg){
			$this->elementString=$arg;
		}
		
		public function setImplementsClass($arg){
			$this->implementsClass = $arg;
		}
		
		public function setFormName($arg){
			$this->formName = $arg;	
		}
				
		public function getFormName(){
			return $this->formName;
		}
		
		public function getImplementsClass(){
			if($this->implementsClass==""){
				print("\n<!--Error No Class File Defined for element!-->");
			}else{
				return $this->implementsClass;
			}
		}
		
		public function getHandle(){
			return $this->handle;
		}
		
		public function getName(){
			return $this->name;
		}
		
		public function getElementString(){
			return $this->elementString;
		}
				
		public function produceElement(){
			$loader = getClassLoader();
			$loader->loadClassByName($this->getImplementsClass());
			$className = $this->getImplementsClass();
	       	$result = new $className;
  			if($result){
				$result->setId($this->getName());
				$result->setSelectionComment($this->getSelectionComment());
				$result->setFormId($this->getFormName());
				$result->setElementString($this->getElementString());
				$result->setRequired($this->getRequired());
				$result->setOrder($this->getOrder());
      		}else{
				print("<!--EMBEDDABLE:insertion of class failed, file: ".$this->getImplementsClass()."-->\n");	 	
       		}
   			return $result;
		}
		
		public function listing(){
			$listing = "<br>\n<a href='?page=formMaker&formMaker=".$this->getHandle()."'>Edit</a>&nbsp;&nbsp;&nbsp;&nbsp;".$this->getName()." &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Element Key: &nbsp;".$this->getHandle()."&nbsp;&nbsp;<br><br>\n";
			return $listing;
		}		
	}
}
?>