<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("AbstractDAO")){
	abstract class AbstractDAO{
		protected $resultSet;
		protected $dataConnection;
		protected $table;

		abstract public function obtainRow($uniqueKey);
		abstract public function obtainNRows($groupKey);
		abstract public function obtainAllRows();
		abstract public function deleteRow($itemKey);
		abstract public function insertRow($rowData);
		abstract public function updateRow($rowData);
		abstract public function getBean($row);
		
		public function __toString(){ return '';   // #60-class: always return a string
		//interpret the resultSet.	
		}

		protected function query($string){
			//Log the query?
			$this->resultSet = $this->dataConnection->query($string);
		}  

		 /* From the PHP Manual: a best practice for avoiding injection attacks*/
		/* --All database fields should pass through here. 					 */	 	 
		protected function quote($value){
			// Stripslashes
			if (get_magic_quotes_gpc()) {
				$value = stripslashes($value);
			}
			// Quote if not a number or a numeric string
			if (!is_numeric($value)) {
				$value = "'" . mysql_real_escape_string($value) . "'";
			}
			return $value;
		}
				
		protected function buildInsertSQL($assocArr){
			$sql = "INSERT INTO ".$this->table;
			$cols = " (";
			$values = "VALUES(";
			$index = 1;
			$fill = count($assocArr);
			$value = current($assocArr);
			$key = key($assocArr);
			$cols .= "`".$key."`";
			$values .= $value;			
			while($index++ <$fill){
				$value = next($assocArr);
				$key = key($assocArr);
				$cols .= ",`".$key."`" ;
				$values .= ",".$value;
			}
			$sql = $sql.$cols.")".$values.")";
			return $sql;		
		}
				
		//$table will be something like table or paw.table
		//fields may be *
		//$whereClause WHERE `key`='theKey'
		protected function buildSelectSQL($whereClause){
			$sql = "SELECT * FROM " . $this->table . " " . $whereClause;
			return $sql;
		}
		
		protected function buildDeleteSQL($whereClause){
			$sql = "DELETE FROM " . $this->table . " " . $whereClause; 
			return $sql;
		}
		
		protected function select($whereClause){
			$this->query($this->buildSelectSQL($whereClause));
			if($this->resultSet){
				return mysql_num_rows($this->resultSet);
			}else{
				return NULL;	
			} 			
		}
		
		protected function delete($whereClause){
			$sqlStatement = $this->buildDeleteSQL($whereClause);			
			$this->query($sqlStatement);
		}
		
		protected function returnAllBeans($rows){
			$beansArray = NULL;
			for($index=0;$index<$rows;$index++){
				$row = mysql_fetch_assoc($this->resultSet);
				$beansArray[$index] = $this->getBean($row);
			}
			
			return $beansArray;				
		}	
	}
}
?>