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
$prodDAO = new ProductDAO();
//Select all elements
print("<br><br>Here all products in the catelog are displayed.<br>");
$rows = $prodDAO->obtainAllRows();
if(!empty($rows)){
	foreach($rows as $product){
		print($product->__toString());
	}	
}

print("<br><br>Here all jumps are displayed.<br>");
//Select a category of elements
$rows = $prodDAO->obtainNRows(1);
if(!empty($rows)){
	foreach($rows as $product){
		print($product->__toString());
	}	
}

print("<br><br>Item 10 or Teeter is displayed.<br>");
//Select a category of elements
$product = $prodDAO->obtainRow(10);
if(isset($product)){
	print($product->__toString());
}

PersistentObjectManager::pack($_SESSION['POM']);	
?>