<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if (!class_exists("ProductList")){
	class ProductList{
	
		private $products;
		
		
		public function __construct($category){
			$this->lookupProducts($category);
		}

		public function __toString(){
			$listing = '';
			foreach($this->products as $product){
				$listing .= $product->catalog_string() . "<br /><br />";
			}
			return $listing;
		}

		private function lookupProducts($category){
			$catDAO = new CatalogDAO();
			$this->instantiate_products($catDAO->select_products_by_category($category));
		}
			
		
		public function getProduct($key){
			return $this->products[$key];
		}
		
		private function instantiate_products($assoc_arr){
			$this->products = array();
			foreach ($assoc_arr as $prod_data){
				array_push($this->products,Product::sql_assoc_array($prod_data));
			}			
		}		
				
	}
}
?>