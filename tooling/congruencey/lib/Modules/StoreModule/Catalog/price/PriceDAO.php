<?php
/*
 * Created on Jun 25, 2007
 *
 * 
 * Congruency The web management system.
 * 
 * Copyright (C) 2006 Steven Peterson

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
Steven Peterson,2234 4th Ave NW, Calgary, AB T2N 0N7 Canada. steven.peterson@rogers.com 
 */
if(!class_exists("PriceDAO")){
	class PriceDAO{

		public function __construct(){
			$this->dataConnection = DataConnection::CreateConnection(MYSQL_SERVER,MYSQL_STORE_DATABASE,STORE_LOGIN,STORE_PASSWORD);
			$this->dataConnection->open();	
		}

		public function __toString(){
			
		}

		public function get_composite_prices($product_key){
			$product_key = ValidateFields::validateNumericKey($product_key);
			if(isset($product_key)){
				$result = array();
   	    		$statement = "SELECT Price,Description From Item_Price_Binding	INNER JOIN  Price_List ON Price_List.Key = Item_Price_Binding.ItemKey WHERE product_grouping = '$product_key'";
				$resultSet = $this->dataConnection->query($statement);
				if($resultSet){
					$rows = mysql_num_rows($resultSet);
					while($rows-- > 0){
						array_push($result,mysql_fetch_assoc($resultSet));
					}
					mysql_free_result($resultSet);							
				}
				return $result;	   	    		
   	  		}
		}
		
		
		
		public function get_all_prices(){
			$result = array();
   	    	$statement = "SELECT * From Item_Price_Binding	INNER JOIN  Price_List ON Price_List.Key = Item_Price_Binding.ItemKey";
			$result_set = $this->dataConnection->query($statement);
			if($result_set){
				$result = $this->sort_result_set($result_set);
			}
			return $result;   	  		
		}
		
		/*
		public function get_only_prices(){
			$result = array();
   	    	$statement = "SELECT * FROM Price_List";
			$result_set = $this->dataConnection->query($statement);
			if($result_set){
				$rows = mysql_num_rows($result_set);
				while($rows-- > 0){
					$record = mysql_fetch_assoc($result_set);
					array_push($result,$record);					
				}
			mysql_free_result($result_set);
			}
			return $result;   	  					
		}*/
		
		public function verbose_item_list(){
			$result = array();
   	    	$statement = 

"SELECT * From Item_Price_Binding 
RIGHT JOIN Price_List ON Price_List.Key = Item_Price_Binding.ItemKey 
LEFT JOIN Products ON Products.key = Item_Price_Binding.product_grouping";

			$result_set = $this->dataConnection->query($statement);
			if($result_set){
				$rows = mysql_num_rows($result_set);
				while($rows-- > 0){
					$record = mysql_fetch_assoc($result_set);
					array_push($result,$record);					
				}
			mysql_free_result($result_set);
			}
			return $result;   	  					
		}
		
		
		
		
		
		public function get_only_bindings(){
			
		}

		
		public function get_price($key){
			$result = NULL;
			$v_key = ValidateFields::validateNumericKey($key);
			if(isset($v_key)){
   	    		$statement = "SELECT * FROM Price_List WHERE `Key`=$v_key";
				$result_set = $this->dataConnection->query($statement);
				if($result_set){
					$result = mysql_fetch_assoc($result_set);
					mysql_free_result($result_set);				
				}
				return $result;
			}   	  		
		}
		
		
		
		
		private function sort_result_set($result_set){
			$records = array();
			$rows = mysql_num_rows($result_set);
			while($rows-- > 0){
				$record = mysql_fetch_assoc($result_set);
				$index = $record['product_grouping'];
				if($index != ""){
					if(!isset($records[$index])){
						$records[$index]=array();
					}
					array_push($records[$index],$record);
				}
			}
			mysql_free_result($result_set);
			return $records;									
		}			
			

		public function __sleep(){
			$this->dataConnection->close();
		}
	
		public function __wakeup(){
			$this->dataConnection->open();
		}

	}
} 
?>
