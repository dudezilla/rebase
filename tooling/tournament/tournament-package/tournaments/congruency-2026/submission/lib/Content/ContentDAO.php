<?php
/*
 * Created on Feb 11, 2007
 *
 * 
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
