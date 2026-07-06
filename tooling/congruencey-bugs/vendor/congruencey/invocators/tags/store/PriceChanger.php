<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
  if(!class_exists("PriceChanger")){
	final class PriceChanger extends Admin_Tag{

		public function __construct($arguments){
		}
		
		protected function run_admin_function(){
			$price_list = new PriceList();	
			$result = $price_list->get_all_prices();
			return $result;
		}
	}
 }
 
 
?>
