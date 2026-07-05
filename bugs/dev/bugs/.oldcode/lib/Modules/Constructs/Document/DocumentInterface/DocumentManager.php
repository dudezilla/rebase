<?php
/*
 * Created on Feb 17, 2007
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