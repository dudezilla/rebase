<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!function_exists("getClassLoader")){
	function getClassLoader(){
		if(class_exists("PersistentObjectManager") && PersistentObjectManager::isConstructed()){
			return PersistentObjectManager::getClassLoader();
		}else{
			if(!isset($_SESSION['loader'])){
				$_SESSION['loader'] = ClassLoader::loaderFactory(LIB);
				require_once("AutoLoader.php");
			}
			return $_SESSION['loader'];
		}
	}
}
?>