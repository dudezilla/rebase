<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("Order")){
	class Order {
		private	$orderNumber;
		private	$clientName;
		private	$itemDescription;
		private	$comment;
		private	$clientEmail;
		private	$phone;
		private	$date;
		private	$unixKey;


	public function Order(){	
	}
	
	public function getKey(){
	}
	
	public function getOrderTrackingNumber(){
		return $this->orderNumber . $this->unixKey . $this->orderNumber;
	}

	public function getOrderNumber(){
		return $this->orderNumber;
	}

	public function getPhone(){
		return $this->phone;
	}

	public function getClientName(){
		return $this->clientName;
	}

	public function getItemDescription(){
		return $this->itemDescription;
	}

	public function getComment(){
		return $this->comment;
	}

	public function getClientEmail(){
		return $this->clientEmail;
	}

	public function getDate(){
		return $this->date;
	}

	public function getUnixKey(){
		return $this->unixKey;
	}
	
	public function getRepComment(){
		return $this->repComment;
	}

	public function setOrderNumber($arg){
		$this->orderNumber = $arg;
	}

	public function setClientName($arg){
		$this->clientName = $arg;
	}

	public function setItemDescription($arg){
		$this->itemDescription = $arg;
	}
	
	public function setPhone($arg){
		$this->phone = $arg;
	}

	public function setComment($arg){
		$this->comment = $arg;
	}

	public function setClientEmail($arg){
		$this->clientEmail = $arg;
	}

	public function setDate($arg){
		$this->date = $arg;
	}

	public function setUnixKey($arg){
		$this->unixKey = $arg;
	}
	
	public function setRepComment($arg){
		$this->repComment = $arg;
	}


	public function toString(){
	   return "Name: " . $this->clientName . " \n" .
	   "Client Email: " . $this->clientEmail . "\n" .
	   "Client Phone: " . $this->phone . "\n" .
	   "Order Confirmation Key(OCK): " . $this->getOrderTrackingNumber() . "\n" .
	   "Date: " . $this->date . "\n" .
	   "Item Description:\n" .
	   $this->itemDescription . "\n" .
	   "Customer Comments:\n" .
	   $this->comment . "\n";
	}


	public function table(){
	   return "<table>" .
	   $this->getRow("Name:", $this->clientName) .
	   $this->getRow("Client Email:", $this->clientEmail) .
	   $this->getRow("Client Phone:", $this->phone) .
	   $this->getRow("Order Confirmation Key(OCK):",$this->getOrderTrackingNumber()) .
	   $this->getRow("Date:", $this->date) .
	   "</table>\n" .
	   "<b>Item Description:</b> \n<br />" .
	   $this->itemDescription .
	   "\n<br />\n<b>Customer Comments: </b>\n<br />" .
	   $this->comment .	"\n";
	}

	public function isEqual($order){
		$this->orderNumber == $order->getOrderNumber();
		$this->clientName == $order->getClientName();
		$this->itemDescription == $order->getItemDescription();
		$this->comment == $order->getComment();
		$this->clientEmail == $order->getClientEmail();
		$this->phone == $order->getPhone();
		$this->date == $order->getDate();
		$this->unixKey == $order->getUnixKey();
	}
	
	public function getRow($fieldName, $fieldValue){
	   return "\n   <tr>\n      <td><b>" . $fieldName . "</b>\n      </td>\n      <td>\n         " . $fieldValue . "\n      </td>\n   </tr>";	
	}

	}
}

?>