<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("CatalogDAO")){
	class CatalogDAO {
		private $dataConnection;

		const FIELD_NAME_CATEGORY_NAME = 'name';
		const FIELD_NAME_DESCRIPTION = 'description';
		const FIELD_NAME_CATEGORY_ID = 'key';
		const FIELD_NAME_PICTURE = 'picture';

		const FIELD_NAME_PRODUCT_NAME = 'name';
		const FIELD_NAME_PRODUCT_DESCRIPTION = 'description';
		const FIELD_NAME_PRODUCT_ID = 'key';
		const FIELD_NAME_PRODUCT_CATEGORY = 'category';
		const FIELD_NAME_PRODUCT_PICTURE = 'picture';

						
		public function __construct(){
			$this->dataConnection = DataConnection::CreateConnection(MYSQL_SERVER,MYSQL_STORE_DATABASE,STORE_LOGIN,STORE_PASSWORD);
			$this->dataConnection->open();		
		}

		public function select_all_categories(){
			$result = array();
				$selectString = "SELECT * FROM Categories";
				$resultSet = $this->dataConnection->query($selectString);
				if($resultSet){
					$rows = mysql_num_rows($resultSet);
					while($rows-- > 0){
						array_push($result,mysql_fetch_assoc($resultSet));
					}
					mysql_free_result($resultSet);							
				}
			return $result;	
		}


		public function select_products_by_category($key){
			$result = array();
			$itemKey = ValidateFields::validateNumericKey($key);
			if(isset($key)){
				$selectString = "SELECT * FROM Products where category=$key";
				$resultSet = $this->dataConnection->query($selectString);
				if($resultSet){
					$rows = mysql_num_rows($resultSet);
					while($rows-- > 0){
						array_push($result,mysql_fetch_assoc($resultSet));
					}
					mysql_free_result($resultSet);							
				}
			}
			return $result;	
		}
		
		
		public function select_all_products(){
			$result = array();
			$selectString = "SELECT * FROM Products";
			$resultSet = $this->dataConnection->query($selectString);
			if($resultSet){
				$rows = mysql_num_rows($resultSet);
				while($rows-- > 0){
					$record = mysql_fetch_assoc($resultSet);
					$result[$record['key']] = $record;
				}
				mysql_free_result($resultSet);									
			}
			return $result;	
		}
		
		
		
		


		public function get_product_details($key){
			$result = NULL;
			$itemKey = ValidateFields::validateNumericKey($key);
			if(isset($key)){
				$selectString = "SELECT Content FROM Store_Content_Blocks WHERE productID=$key ORDER BY display_order";
				$resultSet = $this->dataConnection->query($selectString);
				if($resultSet){
					$rows = mysql_num_rows($resultSet);
					while($rows-- > 0){
						$row = mysql_fetch_assoc($resultSet);
						$result .= $row['Content'];
					}
					mysql_free_result($resultSet);							
				}
			}
			return $result;	
		}


		public function get_category_details($key){
			$result = NULL;
			$itemKey = ValidateFields::validateNumericKey($key);
			if(isset($key)){
				$selectString = "SELECT Content FROM Store_Content_Blocks WHERE categoryID=$key ORDER BY display_order";
				$resultSet = $this->dataConnection->query($selectString);
				if($resultSet){
					$rows = mysql_num_rows($resultSet);
					while($rows-- > 0){
						$row = mysql_fetch_assoc($resultSet);
						$result .= $row['Content'];
					}
					mysql_free_result($resultSet);							
				}
			}
			return $result;	
		}
					
		public function __sleep(){
			$this->dataConnection->close();
		}
	
		public function __wakeup(){
			$this->dataConnection->open();
		}
			
	}
/*
		public function __construct(){
			$this->table = "paw.category";
		}

		public function obtainRow($key){
			$key = ValidateFields::validateNumericKey($key);
   	    	$selectString = "WHERE `key`=". $key;
			$rows = $this->select($selectString);
			return current($this->returnAllBeans($rows));
		}
		
		public function obtainNRows($groupKey){
			return $this->obtainAllRows();
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
			$bean = new Category();
			$bean->setName($row['name']);
			$bean->setNumberKey($row['key']);
			$bean->setDescription($row['description']);
			$bean->setPicture($row['picture']);
			return $bean;
		} 
   	}	*/
}
?>