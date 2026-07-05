<?php
/*
 * Created on Feb 19, 2007
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
if(!class_exists("Tag_Parser")){
	class Tag_Parser{		
				
		private $tag;				//Full tag example: Foo(arg1)(arg2)
 		private $tag_arguments;		//Tag arguments arg1 and arg2. Contained in an argument wrapper class.
 		private $function_name;		//Name of the tag function to load. Foo
 		private $tag_invocation;	//Raw invocation string as received (declared: no dynamic property; PHP 8.2+)
		
		
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
