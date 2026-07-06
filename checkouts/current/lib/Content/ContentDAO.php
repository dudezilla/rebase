<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("ContentDAO")){
	class 	ContentDAO{
		
		const FIELD_NAME_DESCRIPTION = 'Description';
		const FIELD_NAME_CONTENT = 'Content';
		const FIELD_NAME_CONTENT_ID = 'ContentID';
		const FIELD_NAME_TITLE = 'Title';
	
		private $dataConnection;
	
		public function __construct(){
			$this->dataConnection = DataConnection::CreateConnection(MYSQL_SERVER,MYSQL_CONT_DATABASE,CONTENT_LOGIN,CONTENT_PASSWORD);
			$this->dataConnection->open();		
		}
		
		public function lookupContent($key){
			$content = NULL;
			$key = DataConnection::quote($key);
			$statement =  "SELECT * FROM Content_Blocks WHERE ContentID = $key ";;			
			$resultSet = $this->dataConnection->query($statement);
			if($resultSet){
				$rows=mysql_num_rows($resultSet);
				if($rows == 1){		//Only want 1 result set anything else is an error.
					$content = mysql_fetch_assoc($resultSet);
					mysql_free_result($resultSet);
				}
			}
			return $content;		
		}
		
		
		public function __sleep(){
			$this->dataConnection->close();
		}
	
		public function __wakeup(){
			$this->dataConnection->open();
		}


	}
}
?>
