<?php 
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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