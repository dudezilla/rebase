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
$formMan = PersistentObjectManager::getData("FORM_MANAGER");
if(!isset($formMan)){
	PersistentObjectManager::setData("FORM_MANAGER",new FormManager()); 
}
print(ConfigForm::launchConfigForm(6));
PersistentObjectManager::pack($_SESSION['POM']);	
?>