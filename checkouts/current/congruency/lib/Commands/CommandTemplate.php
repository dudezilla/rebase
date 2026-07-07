<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("CommandTemplate")){
	class CommandTemplate implements Command{
	
		private $parameters;

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
			return "Template()";
		}
		
		public function execute(){
			print("<br>executing the Template Command<br>");
		}
		
		private function parseParameters($parameters){
			return NULL;
		}
		
		
	}
}
?>