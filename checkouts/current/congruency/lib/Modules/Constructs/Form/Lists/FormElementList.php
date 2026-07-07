<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("FormElementList")){
	class FormElementList{

		private $elements;

		public function __construct(){
			$elDAO = new FormElementDAO();
			$this->elements = $elDAO->obtainAllRows();
			$this->organize();
		}

		public function __toString(){
			$listing = "";
			foreach($this->elements as $formName=>$formCollection){
				$listing .="<br><br>Form name: $formName<hr>\n";
				foreach($formCollection as $element){
					$listing .= $element->listing();
				}
			}
			return $listing;
		}
		
		private function organize(){
			if(!empty($this->elements)){
				foreach($this->elements as $element){
					$temp[$element->getFormName()][$element->getName()] = $element;	
				}
				$this->elements=$temp;				
			}	
		}		
	}
}
?>