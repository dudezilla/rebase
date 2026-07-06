<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
require_once(CLASS_LOADER_HEADER);
session_start();
getClassLoader();
PersistentObjectManager::getPOM();

$one = PersistentObjectManager::getData("One");
if(empty($one)){
	$one = 0;	
}

$testObject = PersistentObjectManager::getData("testObject");
if(empty($testObject)){
	$testObject = new Category();	
	print ("<br><br>getting new category!<br><br>");
}else{
	print("<br>Here is the class of the testObject: " . get_class($testObject));
}




print("<br>Here is the value of one: " .$one++);
PersistentObjectManager::setData("One",$one);
PersistentObjectManager::setData("testObject",$testObject);


PersistentObjectManager::pack($_SESSION['POM']);	




//$testCommand = CommandTemplate::commandFactory(NULL);
//print("<br><br>" . $testCommand->__toString() . "<br><br>");
//PersistentObjectManager::executeCommands();



?>