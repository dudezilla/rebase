<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("PageMaker")){
		class PageMaker extends AbstractMaker{
	
		public function __construct(){
			$this->keyVariable = "pageMaker";
			$this->formName = "PAGEMAKER";
			$this->selectionLink = "<a href='?page=pageMakerLink'>Choose another page.</a>";
			$this->init();
		}
		
		protected function insertResultKeys($results){
			$results['key'] = $this->key;
			return $results;	
		}
		
		protected function useValidator($prospect){
			return ValidateFields::validatePageKey($prospect);
		}
		
		protected function initFormArray(){
			$result = NULL;
			$pageDAO = new PageDAO();
			$page = $pageDAO->obtainRow($this->key);
			if(isset($page)){
				$pageData['title'] = $page->getTitle();	
				$pageData['description'] = $page->getDescription();	
				$pageData['body'] = $page->getBody();
				$result = $pageData;
			}
			return $result;
		}
	}
}
?>