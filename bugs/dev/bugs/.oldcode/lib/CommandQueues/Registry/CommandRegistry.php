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