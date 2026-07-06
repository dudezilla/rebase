<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("DocumentDAO")){
	class DocumentDAO{
		
		private $dataConnection;

		const FIELD_NAME_CONTENT = 'Content';
		const FIELD_NAME_TEMPLATE_ID = 'TemplateID';
		const FIELD_NAME_TITLE = 'Title';
		const FIELD_NAME_DOCUMENT_ID = 'DocumentID';
		const FIELD_NAME_DESCRIPTION = 'Description';
		const FIELD_NAME_CONTENT_ID = 'ContentID';
		
				
		public static function get_Description_Field_Name(){
				return "Description";	
		}
		
		public static function get_Title_Field_Name(){
				return "Title";
		}
		
		public static function get_DocumentID_Field_Name(){
				return "DocumentID";
		}
		
		public function __construct(){
			$this->dataConnection = DataConnection::CreateConnection(MYSQL_SERVER,MYSQL_CONT_DATABASE,CONTENT_LOGIN,CONTENT_PASSWORD);
			$this->dataConnection->open();		
		}

		public function getDocumentData($key){
			$result = NULL;
			$key = ValidateFields::validatePageKey($key);
			if(isset($key)){
				$selectString = "SELECT * FROM Documents" 
				. " INNER JOIN Document_Templates ON Documents.TemplateID = Document_Templates.TemplateID"
				. " WHERE Documents.DocumentID = '$key' ";
				$resultSet = $this->dataConnection->query($selectString);
				if($resultSet){
					$rows = mysql_num_rows($resultSet);
						if($rows == 1){
							$result = mysql_fetch_assoc($resultSet);
							mysql_free_result($resultSet);
						}
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
}
?>
