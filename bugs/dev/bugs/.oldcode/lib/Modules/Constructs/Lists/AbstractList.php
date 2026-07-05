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
if ( !class_exists('AbstractList')){
   abstract class AbstractList{
	
 	   protected $database;
	   protected $fill;
	   protected $orderList;

	   protected abstract function obtainBean($arg);

	   protected function obtainList($table){
	      $result = mysql_query("SELECT * FROM $table ");
	      if ($result){
	   	      $this->fill = mysql_num_rows($result);
	          $bean = mysql_fetch_assoc($result);

	             for ($count=0 ; $count<$this->fill; $count++ ){
	                $this->orderList[ $count ] =  $this->obtainBean( $bean );
	                $bean = mysql_fetch_assoc($result);
	            }
	      }
	   }

	   protected function printList(){
	      if ($this->fill > 0){
	         $index = 0;
	         do{
	            $this->orderList[$index++]->table();
	   	     }while($index < $this->fill);
	      }else{
	         print("No Items.");
	      }
	   
	    }
	 
	   public function toHTML(){
	      $this->printList();	
	   }

	   public function getFill(){
	      return $this->fill;
	   }
	   
	   
	   public function selectionBox(){
	   }
	   
//	   <OPTION SELECTED value="jex6.htm">Page 1
//     <OPTION value="jex7.htm">My Cool Page
	   
	   
	   
	   
   }
}
?>