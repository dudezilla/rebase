<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
  if(!class_exists("CategoryView")){
	class CategoryView implements Tag_Interface{

		private $category_id;
		private $category_string;

       
		public function __construct($arguments){
			$this->category_id = $arguments->top();
			$catalogDAO = new CatalogDAO();
			$this->category_string = $catalogDAO->get_category_details($this->category_id);
		}
		
		public function get_document(){
			return $this->category_string;              
		}		
	}
  }	
?>
