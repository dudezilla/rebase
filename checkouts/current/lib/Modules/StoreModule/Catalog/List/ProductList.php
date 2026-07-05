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
if (!class_exists("ProductList")){
	class ProductList{
	
		private $products;
		
		
		public function __construct($category){
			$this->lookupProducts($category);
		}

		public function __toString(){
			$listing = NULL;
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