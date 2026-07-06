<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("CommandInterfaceObject")){
	class CommandInterfaceObject{
		private $commandQueue;
		private $commandClasses;
		private $commandRegistry;
		
		public function __construct(){
			$this->commandRegistry = new CommandRegistry();
			$this->commandQueue = array();
		}	
		
		public function enqueueCommand($command){
			//print("<br />enqueueing command" . $command->__toString() . "<hr />");
			array_push($this->commandQueue,$command);				
		}

		public function execute(){
			/*
			foreach($this->commandQueue as $command){
				CommandInterpreter::executeCommand($command);				
			}*/

			//Odd array_pop calls reset after every call. Lets use reset on the first run to be consistent.
			reset($this->commandQueue);
			while(!empty($this->commandQueue)){
				CommandInterpreter::executeCommand(array_pop($this->commandQueue));
			}
			
			
		}	
		
	}
}
?>