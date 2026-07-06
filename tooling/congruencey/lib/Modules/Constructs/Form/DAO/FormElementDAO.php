<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("FormElementDAO")){
	class FormElementDAO extends AbstractDAO{
		
		protected $dataConnection;
		
		//Duh...
		public function __construct(){
			$this->table = "forms";
			$this->dataConnection = DataConnection::CreateConnection(MYSQL_SERVER,MYSQL_FORM_DATABASE,FORM_LOGIN,FORM_PASSWORD);
			$this->dataConnection->open();
		}

		//Obtain a single form element.
		public function obtainRow($key){
			$key = ValidateFields::validateNumericKey($key);
			if(isset($key)){
				$key = $this->quote($key);
   	    		$selectString = "WHERE `key`=". $key;
				$rows = $this->select($selectString);
				$beanArray = $this->returnAllBeans($rows);
				if(isset($beanArray[0])){
					return $beanArray[0];	
				}
			}
			return NULL;	
		}

		//Obtain N formElements where the formName is the same...
		//Returns all the elements of a specific form....
		public function obtainNRows($key){
			$key = ValidateFields::validateFormKey($key);
			if(isset($key)){
				$key = $this->quote($key);
   	    		$selectString = "WHERE `formName`=". $key;
				$rows = $this->select($selectString);
				return $this->returnAllBeans($rows);
			}else{
				return NULL;	
			}
		}
		
		//Return All formElements in the database
		public function obtainAllRows(){
			$rows = $this->select("");
			return $this->returnAllBeans($rows);
		}
		
		//Delete a formElement designated by formKey
		public function deleteRow($formKey){
   	    	$formKey = ValidateFields::validateNumericKey($formKey);
   	    	if(isset($formKey)){
				$formKey = $this->quote($formKey);
				$this->delete("WHERE `key`=$formKey");
   	    	}
		}
		
		//insert a form element
		//rowData is an associative array.
		//(col1=>value1,col2=>value2,col3=>value3,...,coln=>valuen)
		//each column in the database (other than auto-incrementing primary keys)  
		//will have a value indexed by col.
		public function insertRow($rowData){
			if(!empty($rowData)){
				foreach($rowData as &$value){
					$value = $this->quote($value);	
				}
				$insertString = $this->buildInsertSQL($rowData);
				$this->query($insertString);
			}
		}
		
		/*
			Delete a row.
			Insert a row.
			
			Similar to MySQL's update extension...
		
		*/
		public function updateRow($rowData){
			$this->deleteRow($rowData['key']);
			$this->insertRow($rowData);
		}
		
		public function getBean($sqlArray){
			return FormElementBean::sql_assoc_array($sqlArray);
		}
		


	}
}
?>