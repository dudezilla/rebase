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
if(!class_exists("CategoryList")){
	class CategoryList{
		private $allCategories;

		public function __construct(){
			$this->getAllCategories();
		}

		public function __toString(){
			$listing = NULL;
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