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