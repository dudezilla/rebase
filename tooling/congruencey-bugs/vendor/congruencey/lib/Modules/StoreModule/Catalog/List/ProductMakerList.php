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
if (!class_exists("ProductMakerList")){
	class ProductMakerList{
	
		private $allProducts;
		
		public function __construct(){
			$this->getAllProducts();
		}

		public function __toString(){
			$listing = $this->getSummary();
			foreach($this->allProducts as $product){
				$listing .=  $this->productMakerLink($product);	
			}
			return $listing;
		}

		private function getAllProducts(){
			$prodDAO = new ProductDAO();
			$this->allProducts = $prodDAO->obtainAllRows();
		}
		
		private function getSummary(){
			return "\n<br> <b>Available products:" . count($this->allProducts) . "</b><hr>";
		}		
		
		public function getProduct($key){
			return $this->allProducts[$key];
		}
		
		private function productMakerLink($product){
			$productString = 
			 "\n &nbsp; &nbsp; &nbsp; <a href='?page=productMaker&productMaker=". $product->getKey() ."'>Edit</a> &nbsp; " 
			. $product->toHTML();
			return $productString;
		}
	}
}
?>