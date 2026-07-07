<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("Controller")){
class Controller{

		private static $display_string;

		private function __construct(){}
		
		public static function control(){
			if(isset($_GET['page'])){
				$post_validated = ValidateFields::validatePageKey($_GET['page']);	
			}else{
				$post_validated = "catalog";
			}
			self::display($post_validated);
			print(self::$display_string);
		}


		public static function set_display_string($display){
			self::$display_string = $display;
		}


		public static function display($document_id){
			$document = DocumentManager::get_document( $document_id );
		//under what conditions does DocumentManager not return a document?
		//seemingly never WHY DID I PUT THIS IN?
		/*if(!isset($document)){
			$document = DocumentManager::get_document( "catalog" );						
		}*/
			PersistentObjectManager::setData('WORKING_PAGE',$document);	
			self::set_display_string($document->__toString());
			PersistentObjectManager::executeCommands();
		}


	}
}
?>