<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!function_exists("__autoLoad")){
	function __autoLoad($className){
		$loader = getClassLoader();
		$loader->loadClassByName($className);
	}			
}
?>