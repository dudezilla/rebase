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
if(!class_exists("PersistentObjectManager")){
	class PersistentObjectManager{
		private static $pOM;
		private static $constructed;

		private $classLoader;
		private $data;
		private $classes;
		private $commandQueue;	
		
		public static function destroySession(){
			self::$pOM = NULL;
			self::$constructed = NULL;
			session_destroy();
			session_start();
			getClassLoader();
			self::getPOM();
			include(BIN."Initialize_POM.php");
		}	
				
		public static function isConstructed(){
			return self::$constructed;
		}

		public static function enqueueCommand($command){
			if(self::isConstructed()){
				self::$pOM->commandQueue->enqueueCommand($command);
			}else{
				print("<br />Error, Cannot enqueue command ". $command->__toString() ."  No POM!<br />");
			}
		}
		
		public static function getPOM(){
			if(!isset(self::$pOM)){
				if(isset($_SESSION['POM'])){
					self::unpack($_SESSION['POM']);	
					unset($_SESSION['POM']); //not really necessary... may avoid confusion. Adds some security.
				}else{
					self::$pOM = new PersistentObjectManager();
				}
			}
			/*
			//Static variables are lost between page loads, this will need to be reset on every page reload. 
			//---Also isset(self::pOM) does not seem to work. 
			The coupling between this module and the class loader are high. - the class loader needs to exist before
			the POM yet I want the class loader inside of the POM. Ideally destroying the POM should result in a new session.   
			Focing a new class loader to be instantiated. 
			*/ 
			self::$constructed = TRUE; 
		}
		
		public static function setData($reference,$data){
			self::$pOM->data[$reference] = $data;
			$className = get_class($data);
			if($className){
				self::$pOM->classes[$className] = 0;
			}
			
		}
				
		public static function getData($reference){
			$persists = self::$pOM;
			if(isset($persists->data[$reference])){
				return $persists->data[$reference];
			}else{
				return NULL;	
			}
		}
		
		public static function getClassLoader(){
			if(isset(self::$pOM)){
				$persists = self::$pOM;
				return $persists->getLoader();
			}
		}

		
		public static function removeData($reference){
			$persists = self::$pOM;
			if(isset($persists->data[$reference])){
				unset($persists->data[$reference]);
			}	
		}
		
		public static function executeCommands(){
			$pom = self::$pOM;
			$pom->commandQueue->execute();
		}
		
		public static function pack(&$location){
			$pOM = self::$pOM;
			$pOM->data = serialize($pOM->data);
			$location = serialize($pOM);
		}
		
		public static function displayContents(){
			if(isset(self::$pOM->data)){
				foreach (self::$pOM->data as $association=>$item){
					print("<br>Association: " . $association . "   Item: " . $item . " <br>");	
				}
			}else{
				print("<br>\nNo data to print!<br>\n");
			}	
		}

		private function __construct(){
			$this->classLoader = $_SESSION['loader'];
			unset($_SESSION['loader']);			
			$this->commandQueue = new CommandInterfaceObject();
			//self::$constructed = TRUE;
		}	

		private function getLoader(){
			return $this->classLoader;	
		}
		
		private static function unpack($pOM){
			$pOM = unserialize($pOM);
			$pOM->loadClasses();
			$pOM->data = unserialize($pOM->data);
			self::$pOM = $pOM;	
		}
		
		private function loadClasses(){
			if(!empty($this->classes)){
				foreach ($this->classes as $class=>$occurs){
					$this->getLoader()->loadClassByName($class);
				}
			}
		}
		
		
		private function get_data_report(){
			$report = '';
			if(!empty($this->data)){
				foreach($this->data as $index=>$content){
					$report .= "<br />index= $index";										
				}
			
			}	
			return $report;
		}
		
	}		
}
