<?php
/*
 * Created on Mar 3, 2007
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
