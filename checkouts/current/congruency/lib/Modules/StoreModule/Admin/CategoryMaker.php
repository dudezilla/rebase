<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("CategoryMaker")){
		class CategoryMaker extends AbstractMaker{
	
		public function __construct(){
			$this->keyVariable = "categoryMaker";
			$this->formName = "CATEGORYMAKER";
			$this->selectionLink = "<a href='?page=categoryMakerLink'>Choose a category to edit.</a>";
			$this->init();
		}
		
		protected function insertResultKeys($results){
			$results['key'] = $this->key;
			return $results;	
		}
		
		protected function useValidator($prospect){
			return ValidateFields::validateNumericKey($prospect);
		}
		
		protected function initFormArray(){
			$result = NULL;
			$catDAO = new CategoryDAO();
			$catBean = $catDAO->obtainRow($this->key);
			if(isset($catBean)){				
				$catArr['name'] = $catBean->getName();
				$catArr['description'] = $catBean->getDescription();
				$catArr['picture'] = $catBean->getPicture();
				$result = $catArr;
			}
			return $result;
		}
	}
}
?>