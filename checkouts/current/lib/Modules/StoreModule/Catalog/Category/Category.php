<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("Category")){
	class Category{
		private $name;
		private $numberKey;
		private $picture;
		private $description;
			
		public static function sql_assoc_array($array){
			$result = new Category();	
			$result->setName($array[CatalogDAO::FIELD_NAME_CATEGORY_NAME]);
			$result->setPicture($array[CatalogDAO::FIELD_NAME_PICTURE]);
			$result->setDescription($array[CatalogDAO::FIELD_NAME_DESCRIPTION]);
			$result->setNumberKey($array[CatalogDAO::FIELD_NAME_CATEGORY_ID]);
			return $result;
		}
	
		public function __construct(){
		}
		
		public function setName($arg){
			$this->name = $arg;
		}

		public function setNumberKey($arg){
			$this->numberKey = $arg;	
		}
		
		public function setDescription($arg){
			$this->description = $arg;
		}
		
		public function setPicture($arg){
			$this->picture = $arg;
		}
		
		public function getDescription(){
			return $this->description;
		}
		
		public function getPicture(){
			return $this->picture;
		}

		public function getNumberKey(){
			return $this->numberKey;
		}
	
		public function getName(){
			return $this->name;
		}
		
		public function catalog_string(){
			$document = PersistentObjectManager::getData("WORKING_PAGE");
			$result = "\n<table width='80%' align='center'>\n<tr>\n<td>" . HTMLFunctions::image("images/category/" . $this->picture,"","") . "</td>\n<td width='80%'>" . HTMLFunctions::link($this->name,"View Category Items","?page=". $document->getKey() . "&category=" . $this->numberKey) . "<br />" . $this->description;    
			$result .= "</td>\n</tr>\n</table>\n"; 
			return $result;
		}
	}
}
?>