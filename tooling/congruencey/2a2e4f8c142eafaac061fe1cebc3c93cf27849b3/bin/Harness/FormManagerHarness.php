<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
require_once(CLASS_LOADER_HEADER);
session_start();
getClassLoader();
$dataCon = new DataConnection();
PersistentObjectManager::getPOM();
$formMan = PersistentObjectManager::getData("formMan");
if(!isset($formMan)){
	$formMan = 	new FormManager();
	PersistentObjectManager::setData("formMan",$formMan); //we have a reference to formMan in the POM!
}else{
	print("<br>Form manager was aquired through the POM!<hr>\n");
	print(serialize($formMan));
	print("<hr>");
	print("Here are the contents of \$_POST<br>\n");
	foreach($_POST as $index=>$value){
		print("<br>" . $index . "=>" . $value . "<br>\n");
	}	
	print("<br><hr>\n");
}
$junk = $formMan->runForm("ZugZug"); //ZugZub does not exist...
print($formMan->runForm("OrderForm"));
//PersistentObjectManager::setData("formMan",$formMan); //we have a reference to formMan in the POM!
PersistentObjectManager::pack($_SESSION['POM']);	
?>