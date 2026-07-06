<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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