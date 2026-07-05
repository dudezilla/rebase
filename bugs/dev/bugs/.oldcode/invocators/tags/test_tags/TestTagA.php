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
