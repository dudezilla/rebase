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
 if(!class_exists("PriceList")){
	class PriceList{
		
		private $catalogDAO;
		private $catalogList;
		
		public function __construct(){
			$this->catalogDAO= new CatalogDAO();
			$this->catalogList = $this->catalogDAO->select_all_products();
		}
		
		public function __toString(){
			
		}
		
		public function get_all_prices(){
			$list_result = "<a href='?page=createPackage'>Create a new package.</a>";
			$list_result .= "&nbsp;&nbsp;&nbsp;&nbsp;<a href='?page=displayItems'>Display all items.</a>";			
			$priceDAO = new PriceDAO();
			$priceArray = $priceDAO->get_all_prices();
				if(!empty($priceArray)){
					foreach($priceArray as $index=>$row){
						if(isset($this->catalogList[$index])){
							$product = Product::sql_assoc_array($this->catalogList[$index]);
							$list_result .= "<div class='Dialog'><a href='?page=deletePackage&packageID=".$this->catalogList[$index]."'><img align=right src='images/icons/x.png' /></a>". $product->catalog_string() ."\n<br />Product ID: <b>$index</b><br />";
						}else{
							$list_result .= "<div class='Dialog'>The following are assigned to an invalid Product ID: <b>$index</b><br />";							
						}
						if(!empty($row)){
							$list_result .= " Records associated with Product ID:<b>" . count($row) . "</b><hr />";
								foreach($row as $record_index=>$record){
									$list_result .= "<br />";									
									if(!empty($record)){
											$list_result .= "&nbsp;&nbsp;<a href='?page=editPrice&priceMaker=".$record['key']."'><img src='images/icons/edit.png' /></a>";
											$list_result .= "<a href='?page=deleteOption&priceMaker=".$record['key']."'><img src='images/icons/x.png' /></a>&nbsp;&nbsp;";
											$list_result .= $record['Description'] . "&nbsp;&nbsp;$".$record['Price']."&nbsp;&nbsp; Reference ID:".$record['key'];
									}
								}
						}
						$list_result .= "<br /><br /><a href='?page=newAssociation'>Make a new association.</a>" .
										"&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href=?page=newItemRecord>Make a new item record.</a>".									
										"</div>";
					}
				}
			return $list_result;
		}
		
		
		
		public function get_by_product($id){
		
		}
	}

 	
 }
?>
