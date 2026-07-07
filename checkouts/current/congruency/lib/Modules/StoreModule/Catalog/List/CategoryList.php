<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("CategoryList")){
	class CategoryList{
		private $allCategories;

		public function __construct(){
			$this->getAllCategories();
		}

		public function __toString(){
			$listing = '';
			for($index=0, $fill=count($this->allCategories); $index < $fill; $index++){
				$category = $this->allCategories[$index];
				$listing .= $category->catalog_string() . "<br /><br />";
			}
			return $listing;
		}

		private function getAllCategories(){
			$catDAO = new CatalogDAO();
			$this->instantiate_categories($catDAO->select_all_categories());
			
		}
		
		private function getSummary(){
			return "\n<br> <b>Available Categories: " . count($this->allCategories) . "</b><hr>";
		}
		
		private function getListing($bean){
			$categoryListing = "<br><br><A HREF='?page=categoryMaker&categoryMaker=". $bean->getNumberKey() ."'>Edit</A>&nbsp;&nbsp;&nbsp;";			
			$categoryListing .= "Category: ". $bean->getName();
			return $categoryListing;
		}
		
		public function editorListing(){
			$listing = $this->getSummary();
			foreach($this->allCategories as $bean){
				$listing .= $this->getListing($bean);
			}
			return $listing;
		}
		
		private function instantiate_categories($assoc_arr){
			$this->allCategories = array();
			foreach ($assoc_arr as $cat_data){
				array_push($this->allCategories,Category::sql_assoc_array($cat_data));
			}			
		}		
	}
}
?>