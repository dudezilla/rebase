<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("Store")){
	class Store implements Container{	
	
		private static $store_container;
		private $container;
		
		
		private function __construct(){
			$this->container = array();
		}
	
		public static function get_container(){
			$store_container = PersistentObjectManager::getData("STORE_CONTAINER");
			if(!isset($store_container)){
				self::$store_container = new self();	
				PersistentObjectManager::setData("STORE_CONTAINER", self::$store_container);
			}
		}
	
	
		public static function add($value,$reference){
			$result = NULL;
			if(isset(self::$store_container->container[$reference])){
				$result = self::$store_container->container[$reference];
			}
			self::$store_container->container[$reference] = $value;
			return $result;
		}


		public static function remove($reference){
			$result = NULL;
			if(isset(self::$store_container->container[$reference])){
				$result = self::$store_container->container[$reference];
				unset(self::$store_container->container[$reference]);
			}
			return $result;						
		}
	}
}
?>
