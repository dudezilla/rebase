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
$test_result = '';
$product='1';
print("<br />Tests mapping product id's and composite objects<br />");
print("<br />product: ");
$priceDAO = new PriceDAO();
$priceArray = $priceDAO->get_composite_prices($product);
if(!empty($priceArray)){
	foreach($priceArray as $row){
		$test_result .= "<br />composite items:" . count($priceArray);
		if(!empty($row)){
			foreach($row as $column=>$value){
				$test_result .= "<br />coloumn:$column | value: $value";
			}
		}
	}
	print($test_result);
}



$test_result = '<br /><br /><br />';
print("<br />Veiw All Composite Objects");
$priceDAO = new PriceDAO();
$priceArray = $priceDAO->get_all_prices();
if(!empty($priceArray)){
	$test_result .= "<br />Composite items:" . count($priceArray);
	foreach($priceArray as $index=>$row){
		$test_result .= "<br /><br />product ID: $index";
		if(!empty($row)){
			$test_result .= "<br />records associated with product ID:" . count($row) . "<br />";
			foreach($row as $record_index=>$record){
				$test_result .= "<br />&nbsp;&nbsp;&nbsp;Record Index = $record_index";
				if(!empty($record)){
					foreach($record as $column=>$value){
						$test_result .= "<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$column = $value";
					}
				}
			}
		}
	}
}
print($test_result);

PersistentObjectManager::pack($_SESSION['POM']);
?>