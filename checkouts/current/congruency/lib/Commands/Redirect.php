<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
 if(!class_exists("Redirect")){
	class Redirect implements Command{
	
		private $page;

		public static function commandFactory($parameters){
			$class = __CLASS__;
			$command = new $class;
			$command->parseParameters($parameters);
			PersistentObjectManager::enqueueCommand($command);
			return $command;	
		}

		private function __construct(){
		}

		public function __toString(){
			return "Redirect($this->page)";
		}
		
		public function execute(){
			Controller::display($this->page);
		}
		
		private function parseParameters($parameters){
			$this->page=$parameters;
		}
		
		
	}
}
 
 
?>
