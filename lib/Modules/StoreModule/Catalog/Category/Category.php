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