<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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
