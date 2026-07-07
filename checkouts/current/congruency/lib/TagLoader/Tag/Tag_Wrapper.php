<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
 if(!class_exists("Tag_Wrapper")){
  	class Tag_Wrapper {
 		private $tag_parser;
 		private $children;
 		private $invocation_string;
 		private $tag; 			
 			
 		/////////////////////invocation	
 		private function __construct(){
 			$this->children = array();
 		}

		public static function create_document_tag($document){
			$document_wrapper = new Tag_Wrapper();
			$document_wrapper->tag = $document;
			return $document_wrapper;
		}
 
 		private static function get_tag_wrapper($invocation_string){
 			$tag_wrapper = new Tag_Wrapper();
 			$tag_wrapper->tag_parser =  Tag_Parser::get_tag_parser($invocation_string);
 			$tag_class = $tag_wrapper->tag_parser->get_function_name();
 			$tag_loader = PersistentObjectManager::getData("TAG_LOADER");
 			$tag_loader->loadClassByName($tag_class);
 			$tag_wrapper->tag = new $tag_class($tag_wrapper->tag_parser->get_tag_arguments());
 			return $tag_wrapper;
  		}
  		
  		///////////////////////////////////////////////////  		
  		public function __toString(){
  			return $this->tag->__toString();	
  		}		
 		
 		public function add_child($invocation_string){
 			//Create a new Tag_Wrapper and tag as well.
 			$tag_wrapper = Tag_Wrapper::get_tag_wrapper($invocation_string);
 			array_push($this->children,$tag_wrapper);
 			return $tag_wrapper;			
 		}
 		
 		public function get_invocation_string(){
 			return $this->invocation_string;	
 		}
 
 		private static function replace_tag($document_string, $replacement_string, $invocation_string ){
			return str_replace( $invocation_string, $replacement_string, $document_string);
		} 
				
		private function identify_tag($document_string){
			$tags = array();
			$matchPattern = TAG_KEY_PREFIX . FUNCTION_NAME . FUNCTION_ARGUMENTS . TAG_KEY_SUFFIX;
			preg_match_all($matchPattern,$document_string,$tags);
			$tags = $tags[0];
			return $tags;
		}
						
		public static function execute_all_tags($tag_wrapper, $depth = 0){
			$result_string = $tag_wrapper->tag->get_document(); //The tag is processed. 
			if ($depth >= 64) { return $result_string; }   // BUG-06: bound tag-render recursion (real nesting <=~6); leave nested tags literal
			$document_string = $result_string;
			$tIA = $tag_wrapper->identify_tag($document_string);
			foreach($tIA as $tag_invocation){
				$child_tag = $tag_wrapper->add_child($tag_invocation);
				$result_substring = self::execute_all_tags($child_tag, $depth + 1);
				$result_string = self::replace_tag($result_string,$result_substring,$tag_invocation);
			}
			return $result_string;
		}		
 	}
 }
?>
