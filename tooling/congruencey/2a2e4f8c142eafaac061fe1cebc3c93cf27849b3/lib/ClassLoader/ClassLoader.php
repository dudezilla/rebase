<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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
