<?php 
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if (!class_exists('DataConnection')){
	class DataConnection {

		private $database;
		private $user;
		private $password;		
		private $link;
		
		
		public static function CreateConnection($server,$database,$login,$password){
			$result = new DataConnection();
			$result->database = $database;
			$result->user = $login;
			$result->password = $password;
			return $result;
		}
		
		private function __construct(){
		}
		
	
		public function open(){
			$this->link = mysql_connect(MYSQL_SERVER, $this->user, $this->password);
		}
		
		public function close(){
			return mysql_close($this->link);
		}
	
	
	     
		public function query($statement){
			//Log the query?
			if(mysql_select_db ($this->database, $this->link) ){
				return mysql_query($statement, $this->link);			
			}			
		}  

		 /* From the PHP Manual: a best practice for avoiding injection attacks*/
		/* --All database fields should pass through here. 					 */	 	 
		public static function quote($value){
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
				
		public static function buildInsertSQL($table,$assocArr){
			$sql = "INSERT INTO `".$table."`";
			$cols = "(";
			$values = "VALUES(";
			$index = 1;
			$fill = count($assocArr);
			$value = current($assocArr);
			$key = key($assocArr);
			$cols .= $key ;
			$values .= $value;			
			while($index++ <$fill){
				$value = next($assocArr);
				$key = key($assocArr);
				$cols .= ",".$key ;
				$values .= ",".$value;
			}
			$sql = $sql.$cols.")".$values.")";
			return $sql;		
		}
				
		//$table will be something like table or paw.table
		//$quantifier may be *
		//$whereClause WHERE `key`='theKey'
		public function buildSelectSQL($table,$quantifier,$whereClause){
			$sql = "SELECT " . $quantifier . " FROM `" . $table . "` " . $whereClause;
			return $sql;
		}
		
		public function buildDeleteSQL($table,$whereClause){
			$sql = "DELETE FROM `" . $table . "` " . $whereClause; 
			return $sql;
		}
		
		public static function mysqlReport(){
			$result = "Verify the success of the database operation:<br>";
			$result .= "Query Information: ". mysql_info();
			$result .= "Errors: <br>\n" . mysql_error() . "<br><br>"; 
			return $result;
		}
		
		
   }
}
?>