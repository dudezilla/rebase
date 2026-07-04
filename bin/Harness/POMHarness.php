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