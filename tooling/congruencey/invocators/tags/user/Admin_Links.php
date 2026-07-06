<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
 
if(!class_exists("Admin_Links")){
	final class Admin_Links extends Admin_Tag{

		public function __construct($arguments){
		}
		
		protected function run_admin_function(){
			return "<div class='Dialog'>Available Administration Options<hr />\n".
			"<a href='?page=showOrders'>Show all orders</a><br />\n".
			"<a href='?page=priceChange'>Change Prices</a><br />\n".
			"</div>\n";			
		}
	}
}
?>
