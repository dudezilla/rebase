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
if(!class_exists("ProductDAO")){
	class ProductDAO extends AbstractDAO{
		
		public function __construct(){
			$this->table = "paw.product";
		}

		public function obtainRow($key){
			$key = ValidateFields::validateNumericKey($key);
			if(isset($key)){
   	    		$selectString = "WHERE `key`=". $key;
				$rows = $this->select($selectString);
				$beansArray = $this->returnAllBeans($rows);
				return current($beansArray);
			}
		}
		
		public function obtainNRows($groupKey){
   	    	$groupKey = ValidateFields::validateNumericKey($groupKey);
   	    	$selectString = "WHERE `category`=".$groupKey;
			$rows = $this->select($selectString);
			return $this->returnAllBeans($rows);
		}
		
		public function obtainAllRows(){
			$rows = $this->select("");
			return $this->returnAllBeans($rows);
		}
		
		public function deleteRow($itemKey){
   	    	$itemKey = ValidateFields::validateNumericKey($itemKey);
			if(isset($itemKey)){
				$this->delete("WHERE `key`=$itemKey");
			}
		}
		
		public function insertRow($rowData){
			if(!empty($rowData)){
				foreach($rowData as &$value){
					$value = $this->quote($value);	
				}
				//$results['index'] = "LAST_INSERT_ID()"; //MAGIC:: Seems as if mysql does this...
				$insertString = $this->buildInsertSQL($rowData);
				$this->query($insertString);
			}
		}
		
		public function updateRow($rowData){
			$this->deleteRow($rowData['key']);
			$this->insertRow($rowData);
		}


		public function getBean($row){
			$bean = new Product();
			$bean->setCategory($row['category']);
			$bean->setName($row['name']);
			$bean->setDescription($row['description']);
			$bean->setKey($row['key']);
			$bean->setPage($row['page']);
			$bean->setPicture($row['picture']);
			return $bean;
		} 

	}
}
?>