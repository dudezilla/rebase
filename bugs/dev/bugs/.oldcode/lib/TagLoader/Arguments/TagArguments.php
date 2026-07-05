<?php 
/*
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
if ( !class_exists('TagArguments')){
   class TagArguments{
       	private $arguments;
		private $count;

    	public static function argumentFactory($functionCall){
    		$tagArg = new TagArguments();
      		$tagArg->setArguments($tagArg->obtainArguments($functionCall));
      		return $tagArg;
		}

    
    	public function getArguments(){
    		return $this->arguments;
    	}

		public function top(){
			return end($this->arguments);
		}
		
		public function pop(){
			$this->count--;
			return array_pop($this->arguments);		
		}
		
		public function finished(){
			return ($this->count > 0);
		}

		private function __construct(){
			$this->count = 0;
		}

		private function setArguments($arg){
			$this->arguments = $arg;
			$this->count = 0;
        	foreach ($this->arguments as &$argument){
      			$argument = $this->removeParenthesis($argument);
      			$this->count++;
      		}
			$this->arguments = array_reverse($this->arguments);
			     	
    	}
	  
		private function obtainArguments($functionCall){
	  		preg_match_all(FUNCTION_ARGUMENT,$functionCall,$result);
			if(!empty($result)){
	  			return $result[0];  //return only complete matches!
			}
		}

		private function removeParenthesis($key){
			$length = strlen($key) - strlen("()");
			$newKey = substr($key,1,$length);
			return $newKey;
		}
		
		public function __toString(){
			$result = "Element Count " . count($this->arguments)."<hr />";
			if(!empty($this->arguments)){
				foreach ($this->arguments as $arg){
					$result	.= $arg . "<br />";
				}
			}
			return $result;	
		}
	}
}
?>