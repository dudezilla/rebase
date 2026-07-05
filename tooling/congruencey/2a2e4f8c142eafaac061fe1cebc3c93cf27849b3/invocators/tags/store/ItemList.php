<?php
/*
 * Created on Jun 26, 2007
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
 if(!class_exists("ItemList")){
	final class ItemList extends Admin_Tag{
		
		public function __construct($arguments){
		
		}
						
		protected function run_admin_function(){
			$price_DAO = new PriceDAO();
			return $this->print_items($this->sort_items($price_DAO->verbose_item_list()));
		}
		
		
		private function print_items($sorted_array){
			$result = "";
			if(isset($sorted_array)){			
				$result = '<table><tr><td></td><td> Description </td><td> Price </td><td>&nbsp;&nbsp;&nbsp;Key</td></tr>';
				foreach($sorted_array as $item_index=>$item_associations){
					$record = current($item_associations);
					$result .= 	"\n<tr style='background:#F5F5F5;'><td>&nbsp;<a href='?page=editPrice&priceMaker=". $record['Key'] ."'><img src='images/icons/edit.png' /></a>".
								"<a href='?page=deleteOption&priceMaker=". $record['Key'] ."'><img src='images/icons/x.png' /></a></td><td>".
								$record['Description'] . "</td><td>$".$record['Price']."</td><td>&nbsp;&nbsp;&nbsp;".$record['Key'] ."</td></tr>\n";
					foreach($item_associations as $association){
					$result .= 	"<tr><td></td><td>" . $association['name'] . "<br />". $association['description'] ."</td><td>ID: ". $association['key'] ." </td><td> &nbsp;<a href='?page=editAssociation&association=".$association['key'] ."'><img src='images/icons/edit.png' /></a><a href='?page=deleteAssociation&association=". $association['key'] ."'><img src='images/icons/x.png' /></a></td></tr>\n";
														
					}
				}
				$result .= '</table>';
			}
			return $result;			
		}
		
		
		
		private function sort_items($assoc_array){
			$sorted_records = array();
				if(isset($assoc_array)){
					foreach($assoc_array as $record){
						if( isset($record["Key"] )){
							if(!isset($sorted_records[$record["Key"]])) 
								$sorted_records[$record["Key"]] = array($record);
						}
					}
				}

			return $sorted_records;
		}
	}
 }
?>
