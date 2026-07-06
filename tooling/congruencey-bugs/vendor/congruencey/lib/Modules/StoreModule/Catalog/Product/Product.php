<?php 
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("Product")){
	class Product{
		private $name;
		private $key;
		private $description;
		private $category;
		private $picture;
		
		public static function sql_assoc_array($array){
			$result = new Product();	
			$result->setName($array[CatalogDAO::FIELD_NAME_PRODUCT_NAME]);
			$result->setPicture($array[CatalogDAO::FIELD_NAME_PRODUCT_PICTURE]);
			$result->setDescription($array[CatalogDAO::FIELD_NAME_PRODUCT_DESCRIPTION]);
			$result->setKey($array[CatalogDAO::FIELD_NAME_PRODUCT_ID]);
			$result->setCategory($array[CatalogDAO::FIELD_NAME_PRODUCT_CATEGORY]);
			return $result;
		}
				
		public function Product(){
		}
		
		public function	setName($arg){
			$this->name = $arg;
		}
		
		public function setKey($arg){
			$this->key = $arg;	
		} 	
		public function setDescription($arg){
			$this->description = $arg;	
		} 	

		public function setCategory($arg){
			$this->category = $arg;	
		} 	

		public function setPicture($arg){
			$this->picture = $arg;
			if(!$this->picture){
				$this->picture = "images/blank.gif"; 
			}else{
				$this->picture = "images/catalog/".$this->picture;
			}
		} 

		public function getName(){
			return $this->name;
		}
		
		public function getKey(){
			return $this->key;
		}
		
		public function getDescription(){
			return $this->description;
		}
		
		
		public function getCategory(){
			return $this->category;
		}
		
		public function getPicture(){
			return $this->picture;
		}

		public function catalog_string(){
			$document = PersistentObjectManager::getData("WORKING_PAGE");
			$result =  HTMLFunctions::link($this->name,"Product Details","?page=catalog&product=".$this->key )."<br />\n".$this->description;
			return $result;
		}		
	}
}
?>