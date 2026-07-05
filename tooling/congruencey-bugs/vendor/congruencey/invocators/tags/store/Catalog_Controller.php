<?php
/*
 * Created on Mar 1, 2007
 *
 * 
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
