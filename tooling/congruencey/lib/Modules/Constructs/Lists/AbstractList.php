<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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