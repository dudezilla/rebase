<?php
if (!class_exists("OrderList")){
	class OrderList{
	
		private $allOrders;
		
		public function __construct(){
			$this->getAllOrders();
		}

		public function __toString(){
			$listing = $this->getSummary();
			foreach($this->allOrders as $order){
				$listing .=  $this->orderMakerLink($order);	
			}
			return $listing;
		}

		private function getAllOrders(){
			$orderDAO = new OrderDAO();
			$this->allOrders = $orderDAO->obtainAllRows();
		}
		
		private function getSummary(){
			return "\n<br> <b>Available orders: " . count($this->allOrders) . "</b><hr>";
		}		
		
		public function getOrder($key){
			return $this->allOrders[$key];
		}
		
		private function orderMakerLink($order){
			$orderString = 
			"<div class='Dialog'>\n<br><br>\n<a href='?page=orderMaker&orderMaker=". $order->getKey() ."'>Edit</a> &nbsp; " 
			. $order->table() . "</div>";
			return $orderString;
		}
	}
}
?>