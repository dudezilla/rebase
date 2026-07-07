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
$login='admin@localhost';
$password='new_value';
print("<br />Tests mapping between login, password pair and privilege set.<br />");
print("<br />login: $login, password: $password");
$userDAO = new UserDAO();
$userPrivileges = $userDAO->authenticateUser($login,$password);
print($userPrivileges->__toString());
print("<br />We should have obtained the admin users privilege set.<br />");



$login='steve@localhost';
$password='steves_password';
print("<br />Tests mapping between login, password pair and privilege set.<br />");
print("<br />login: $login, password: $password");
$userDAO = new UserDAO();
$userPrivileges = $userDAO->authenticateUser($login,$password);
print($userPrivileges->__toString());
print("<br />We should have obtained steves users privilege set.<br />");

PersistentObjectManager::pack($_SESSION['POM']);



$login='test@localhost';
$password='test_password';
print("<br />Tests mapping between login, password pair and privilege set.<br />");
print("<br />login: $login, password: $password");
$userDAO = new UserDAO();
$userPrivileges = $userDAO->authenticateUser($login,$password);
print($userPrivileges->__toString());
print("<br />We should have obtained the test users privilege set.<br />");
PersistentObjectManager::pack($_SESSION['POM']);	



$login='admin@localhost';
$password='test_password';
print("<br />Tests mapping between login, password pair and privilege set.<br />");
print("<br />login: $login, password: $password");
$userDAO = new UserDAO();
$userPrivileges = $userDAO->authenticateUser($login,$password);
print($userPrivileges->__toString());
print("<br />We should have obtained no privilege set.<br />");
PersistentObjectManager::pack($_SESSION['POM']);	



?>