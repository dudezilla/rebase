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