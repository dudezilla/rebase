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
if(!class_exists("AbstractMaker")){
	abstract class AbstractMaker{
		protected $key;
		protected $formMan;

		protected $keyVariable;
		protected $selectionLink;
		protected $formName;
		
		abstract protected function insertResultKeys($results);
		abstract protected function useValidator($prospect);
		abstract protected function initFormArray();

		public function init(){
			$this->setKey($_GET[$this->keyVariable]);
			if(isset($this->key)){
				$this->formMan = PersistentObjectManager::getData("FORM_MANAGER");
			}else{
				$this->key = NULL;	
			}
		}
		
		public function control(){
			$makerString = NULL;
			if(isset($this->key)){
				return $this->initializeForm() . $this->runForm();	
			}else{
				$makerString = "<br />Invalid $this->keyVariable supplied. $this->selectionLink";
			}
		}
	
		private function initializeForm(){
			if(!isset($_POST[$this->formName])){
				$formArray = $this->initFormArray();
				if(isset($formArray)){
					$this->formMan->setResults($this->formName,$formArray);
					return "<br />Item $this->key was found and will be used.<hr />";				
				}else{
					$this->formMan->setResults($this->formName,NULL);
					return "<br />Item $this->key was NOT found and can't be used.<hr />";				
				}
			}
		}
	
		public function runForm(){
			$formString = $this->formMan->runForm($this->formName);
			$results  = $this->insertResultKeys($this->formMan->getResults($this->formName));
			PersistentObjectManager::setData($this->formName . "_RESULT",$results);	
			return $formString;
		}
		
		public function setKey($prospect){
			$validated = $this->useValidator($prospect);
			$this->key = $validated;
			return $this->key;		
		}
		
		public function getKey(){
			return $this->key;
		}
	}
}