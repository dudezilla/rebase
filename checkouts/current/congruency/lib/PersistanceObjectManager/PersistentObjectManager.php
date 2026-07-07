<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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
			$className = is_object($data) ? get_class($data) : NULL;   //non-objects (strings, null) are storable too
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
			$pOM->data = json_encode(self::encode_data($pOM->data));   // #45: forms -> JSON envelopes; other objects serialize-fallback
			$location = serialize($pOM);
		}

		/* #45: POM data (de)serialization. FormManager/StandardForm go to inspectable JSON (via their
		   to_array/from_array — no PHP serialize, no StandardForm::__wakeup reliance); any other object
		   (ClassLoader/Document/UserPrivilegeSet/...) falls back to base64(serialize()) inside the JSON. */
		private static function encode_data($data){
			$out = array();
			if(is_array($data)){
				foreach($data as $k=>$v){ $out[$k] = self::encode_value($v); }
			}
			return $out;
		}

		private static function encode_value($v){
			if($v instanceof FormManager){ return array('t'=>'FormManager', 'v'=>$v->to_array()); }
			if($v instanceof StandardForm){ return array('t'=>'StandardForm', 'v'=>$v->to_array()); }
			if(self::hasObject($v)){ return array('t'=>'php', 'v'=>base64_encode(serialize($v))); }
			return array('t'=>'raw', 'v'=>$v);
		}

		private static function decode_data($enc){
			$out = array();
			if(is_array($enc)){
				foreach($enc as $k=>$e){ $out[$k] = self::decode_value($e); }
			}
			return $out;
		}

		private static function decode_value($e){
			if(!is_array($e) || !isset($e['t'])){ return $e; }
			switch($e['t']){
				case 'FormManager':  return FormManager::from_array($e['v']);
				case 'StandardForm': return StandardForm::from_array($e['v']);
				case 'php':          return unserialize(base64_decode($e['v']));
				default:             return isset($e['v']) ? $e['v'] : NULL;
			}
		}

		private static function hasObject($v){
			if(is_object($v)){ return TRUE; }
			if(is_array($v)){ foreach($v as $x){ if(self::hasObject($x)){ return TRUE; } } }
			return FALSE;
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
			$pOM->data = self::decode_data(json_decode($pOM->data, true));   // #45: rebuild forms from JSON, others from fallback
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
