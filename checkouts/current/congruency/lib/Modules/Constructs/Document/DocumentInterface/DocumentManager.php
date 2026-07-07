<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("DocumentManager")){
	class DocumentManager{
		
		private $documentArray;
		private $documentDAO;
		
		private static $document_man;
		
		public function __construct(){
			$this->documentDAO = new DocumentDAO();
			$this->documentArray = array();
		}

		public static function get_document($document_id){
			if(!isset(self::$document_man)){
				self::$document_man = new DocumentManager();	
			}
			$document = self::$document_man->loadDocument($document_id);
			return $document;
		}
					
		private function loadDocument($documentId){
			//Does not implement document caching.
			$document = $this->lookupDocument($documentId);
			return $document;
		}
		
		private function lookupDocument($documentId){
			$document = $this->produceDocument($documentId);
			if(isset($document)){
				$this->documentArray[$documentId] = $document;
				return $this->documentArray[$documentId];
			}	
		}
		
		private function produceDocument($key){
			$document = NULL;
			$documentData = $this->documentDAO->getDocumentData($key);
			if(isset($documentData) ){
				$document = Document::sql_assoc_array($documentData);
			}else{
				$documentData = $this->documentDAO->getDocumentData("invalid");
				if($documentData){
					$document = Document::sql_assoc_array($documentData);
				}
			}
	      	return $document;
		}
	}		
}
?>