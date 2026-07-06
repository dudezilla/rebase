<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
  if(!class_exists("ProductView")){
	class ProductView implements Tag_Interface{

		private $product_id;
		private $product_string;


		public function __construct($arguments){
			$this->product_id = $arguments->top();
			$catalogDAO = new CatalogDAO();
			$this->product_string = $catalogDAO->get_product_details($this->product_id);
		}
		
		public function get_document(){
			return $this->product_string;
		}		
	}
  }	
?>
