<?php 
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
require_once __DIR__ . '/MysqlShimResult.php';   // #25: result class (was in boot/shim.php)

if (!class_exists('DataConnection')){
	class DataConnection {

		private $database;
		private $user;
		private $password;		
		private $pdo;
		
		
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
			// #25: native PDO (was mysql_connect)
			if (defined('CONGRUENCY_SQLITE')) { $this->pdo = new PDO('sqlite:' . CONGRUENCY_SQLITE); }
			else { $this->pdo = new PDO('mysql:host=' . MYSQL_SERVER . ';dbname=' . $this->database, $this->user, $this->password); }
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
		}
		
		public function close(){
			$this->pdo = null; return true;   // #25: native PDO (was mysql_close)
		}
	
	
	     
		public function query($statement){
			// #25: native PDO (was mysql_select_db + mysql_query); MysqlShimResult keeps mysql_num_rows/fetch_assoc consumers working
			if (!isset($this->pdo)) { $this->open(); }
			$stmt = $this->pdo->query($statement);
			if ($stmt === false) { error_log('[DataConnection] query failed: ' . $statement); return false; }
			return ($stmt->columnCount() > 0) ? new MysqlShimResult($stmt->fetchAll(PDO::FETCH_ASSOC)) : true;
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
				$value = "'" . str_replace("'", "''", $value) . "'";
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
			$result .= "Query Information: ". '';
			$result .= "Errors: <br>\n" . '' . "<br><br>"; 
			return $result;
		}
		
		
   }
}
?>