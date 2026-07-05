<?php
if(!class_exists("Orderer")){
	class Orderer{

		private $clientInfo;
		private $orderLocation;
		private $orderArray;

		private function __construct($items,$information){
			$this->orderLocation = $items;
			$this->clientInfo = $information;
			$this->constructContactInfo($items);
		}
		
		public static function orderFactory($items,$information){
			$orderer = new Orderer($items,$information);
			$orderer->insertOrder();
		    $order = $orderer->retrieveOrderFromDB();
		    if(isset($order)){
		       $orderer->sendEmail($order);
		       return $order;
		    }
		}
		
		private function insertOrder(){
			$orderDAO = new OrderDAO();
			return $orderDAO->insertRow($this->orderArray);
		}

		private function retrieveOrderFromDB(){
			$orderDAO = new OrderDAO();
			$order = $orderDAO->obtainRowByUKey($this->orderArray['unixKey']);
			return $order;
		}

		private function constructContactInfo($description){
			$this->orderArray['clientName'] = $this->clientInfo['nameField'];
			$this->orderArray['clientPhone'] = $this->clientInfo['phoneNumber'];
			$this->orderArray['comment'] = $this->clientInfo['comments'];
			$this->orderArray['clientEmail'] = $this->clientInfo['email'];
			$this->orderArray['itemDescription'] = $description;
			$this->orderArray['unixKey'] = time();
		}
		
		public function sendEmail($order){
            $emailBody = "You have a new order \n" .
            "Name: " . $order->getClientName() . "\n" .
            "Email: " . $order->getClientEmail() . "\n" .
            "Phone: " . $order->getPhone() . "\n\n" .
            "Item: " . $order->getItemDescription() . "\n" .
	    	"Comments:\n\n " . $order->getComment() . "\n" ;
	  		return ( mail($this->emailRecipiants(),ORDER_SUBJECT_HEADER,$emailBody,"From:orders@paulsagilityworks.com"));
		}
		
		private function emailRecipiants(){
			return EMAIL_RECIPIANTS;
		}
	}
}
?>