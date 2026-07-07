<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
 if(!class_exists("Catalog_Controller")){
	class Catalog_Controller implements Tag_Interface{

		public function __construct($arguments){
		}
		
		public function get_document(){
			$result = NULL;
			if(isset($_GET['product'])){
				    $product = ValidateFields::ValidateNumericKey($_GET['product']);
					if(isset($product)){
						$result = "<div width='70%' align='center'><<<ContentTag(storkmessage)>>>" .
								"<<<Config_Form_Invocator($product)>>><<<ProductView($product)>>></div>";
					}		
			}else{			
				if( isset($_GET['category']) ){
        			$category = ValidateFields::ValidateNumericKey($_GET['category']);
        			$productList = new ProductList($category);
        			$result = "<<<ContentTag(ProductViewListing)>>>".$productList->__toString()."<<<CategoryView($category)>>>";
				}else{
        			$result = "<<<ContentTag(CategoryViewListing)>>>";
        			$catList = new CategoryList();
        			$result .= $catList->__toString();
				}
			}
			return $result;
		}		
	}		
}
?>
