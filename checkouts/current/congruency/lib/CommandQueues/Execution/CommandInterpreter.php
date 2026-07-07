<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("CommandInterpreter")){
	class CommandInterpreter{	

		private function _construct(){
		}

		public static function executeCommand($command){		
			$command->execute();
		}
	}
}

?>