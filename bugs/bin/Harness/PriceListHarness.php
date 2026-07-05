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