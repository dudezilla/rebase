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
Steven Peterson,2234 4th Ave NW, Calgary, AB T2N 0N7 Canada. steven.peterson@rogers.com
*/
if(!class_exists("ClassLoader")){
	class ClassLoader{
		private $fileList;				//an array of available files. 
		private $modRoot;

		public static function loaderFactory($modRoot){
			//log or throw exception if $modRoot is not set. 
			return new ClassLoader($modRoot);	
		}

		private function __construct($modRoot){
			$this->descendDirectory($modRoot);
			$this->modRoot = $modRoot;
		}


		public function loadClassByName($clientClass){
			if(isset($this->fileList[$clientClass])){
				if(!include_once($this->fileList[$clientClass])){
					//log instead....
					//Could not open class: ...
				}
			}else{
				//log: Class not in directory tree? not available...
			}
		}
		
		
		public function includeByName($clientClass){
			return include($this->fileList[$clientClass]);
		}
		

		//descend the modules directory tree and identify compatible files.
		//rewritten to function correctly under linux.
		private function descendDirectory($moduleRoot){
			$di = new DirectoryIterator($moduleRoot);
			while($di->valid()){
				if(!$di->isDot()){
					if($di->isDir()){
						$this->descendDirectory($di->getPathname());
					}else{
						if($di->isFile()){
							//Index is the filename without a windows style extension.
							$this->fileList[ substr($di->current(),0, strlen($di->current())-4)] =$di->getPathname();
						}
					}
				}
			   $di->next();
			}
					
		}


		public function __toString(){
			$filesAvailable = NULL;
			if(!empty($this->fileList)){
				foreach ($this->fileList as $file=>$path){
					$filesAvailable .= "File: " . $file . "      Path: " . $path . "\n<br>";	
				}
			}
			
			return $filesAvailable;	
		}
		

		public function __clone(){
			//Throw an exception!	
		}
	
	}
}
?>
