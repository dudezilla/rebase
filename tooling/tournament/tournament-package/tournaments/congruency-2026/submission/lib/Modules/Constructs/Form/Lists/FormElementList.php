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