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
include(BIN."Initialize_POM.php");
Controller::control();
PersistentObjectManager::pack($_SESSION['POM']);	
?>