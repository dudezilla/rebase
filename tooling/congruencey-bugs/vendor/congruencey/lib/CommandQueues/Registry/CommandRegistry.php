<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*//*
//	Command registry is included to ease the implementation of 
//  some kind of Access Control List or security system... 
//
//
//
*/

if(!class_exists("CommandRegistry")){
	
	class CommandRegistry{	

		private $registeredClasses;	
		private $classLoader;
	
		public function __construct(){
			$this->classLoader = getClassLoader();
			$this->registeredClasses = NULL;
		}
		
		public function addClass($className){
			if(isset($this->registeredClasses)){
				$this->registeredClasses[0]=$className;				
			}else{
				array_push($this->registeredClasses,$className);
			}
			$this->loadClass($className);
		}		
		
		private function loadClass($className){
			$this->classLoader->loadClassByFilename($className);
		}

		public function __toString(){
			$resultString = NULL;
			foreach($this->registeredClasses as $className){
				$resultString .= "Command's class name: " . $className . "\n<br>";
			}
			return $resultString;
		}
	
	
	}
}
?>