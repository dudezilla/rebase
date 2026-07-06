<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("TestTagA")){
	class TestTagA implements Tag_Interface{
		private $arguments;
		private $document;
		private $calls;
	
		public function __construct($arguments){
			$this->arguments = $arguments;
			$this->calls = $arguments->top();			
		}
		
		public function get_document(){
			$this->process_tag();
			return $this->document;			
		}
				
		private function process_tag(){
			return
			$this->document = "<br><hr>TestTagA Has been Called with value $this->calls " . $this->call_to_self() ;		
		}
		
		private function call_to_self(){
			if($this->calls > 0){
				return " <<<TestTagA(" . --$this->calls . ")>>> ";
			}else{
				return "The base case.";	
			}
		}	
	}		
}
?>
