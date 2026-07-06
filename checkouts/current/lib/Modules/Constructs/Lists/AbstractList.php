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
	      // #25 native PDO (was global mysql_query/num_rows/fetch_assoc)
	      $conn = DataConnection::CreateConnection(MYSQL_SERVER, MYSQL_CONT_DATABASE, CONTENT_LOGIN, CONTENT_PASSWORD);
	      $conn->open();
	      $result = $conn->query("SELECT * FROM $table ");
	      if ($result){
	         $this->fill = count($result->rows);
	         $count = 0;
	         foreach ($result->rows as $bean){
	            $this->orderList[ $count++ ] = $this->obtainBean( $bean );
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