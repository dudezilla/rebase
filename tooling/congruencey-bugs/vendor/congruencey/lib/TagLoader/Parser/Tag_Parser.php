<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("Tag_Parser")){
	class Tag_Parser{		
				
		private $tag;				//Full tag example: Foo(arg1)(arg2)
 		private $tag_arguments;		//Tag arguments arg1 and arg2. Contained in an argument wrapper class.
 		private $function_name;		//Name of the tag function to load. Foo
		
		
		public static function get_tag_parser($full_tag){
			$tag_parser = new Tag_Parser();
			$tag_parser->initialize($full_tag);
			return $tag_parser;
		}
	
		private function __construct(){}
	
		
		public function initialize($tag_invocation){
			$this->tag_invocation = $tag_invocation;
			$this->tag = $this->extract_tag_name($this->tag_invocation);
			if(isset($this->tag)){
				$this->function_name = $this->get_tag_identifier($this->tag);
				$this->tag_arguments = TagArguments::argumentFactory($this->tag);
			}
		}
		
				
		public function extract_tag_name($tag){
			$prefix = strlen( KEY_PREFIX );
			$length = strlen($tag) - strlen( KEY_PREFIX.KEY_SUFFIX );
			$newKey = substr($tag,$prefix,$length); 
			return $newKey;
		}
		
				
		public function get_tag_identifier($tagString){
			preg_match(GET_TAG_IDENTIFIER,$tagString,$matches);
			if(isset($matches[0])){
				return $matches[0];
			}else{
				return NULL;	
			}
		}
		
		
		public function get_full_tag(){
			return $this->tag;
		}
		
		
		public function get_tag_arguments(){
			return $this->tag_arguments;	
		}
		
		
		public function get_function_name(){
			return $this->function_name;	
		}
			
	}
}
?>
