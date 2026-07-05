<?php
if(!class_exists("OrderDAO")){
	class OrderDAO extends AbstractDAO{

		protected $data_connection;

		public function __construct(){
			$this->dataConnection = DataConnection::CreateConnection(MYSQL_SERVER,MYSQL_ORDER_DATABASE,ORDER_LOGIN,ORDER_PASSWORD);
			$this->dataConnection->open();
			$this->table = "orders";
		}

		public function obtainRow($key){
			$key = ValidateFields::validateNumericKey($key);
			if(isset($key)){
   	    		$selectString = "WHERE `orderNumber`=". $key;
				$rows = $this->select($selectString);
				$orders = $this->returnAllBeans($rows);
				return current($orders);
			}else{
				return NULL;	
			}
		}
		
		public function obtainRowByUKey($key){
			$key = ValidateFields::validateNumericKey($key);
			if(isset($key)){
   	    		$selectString = "WHERE `unixKey`=". $key;
				$rows = $this->select($selectString);
				$orders = $this->returnAllBeans($rows);
				return current($orders);
			}else{
				return NULL;	
			}
		}


		public function obtainNRows($groupKey){
			return $this->obtainAllRows();
		}
		
		public function obtainAllRows(){
			$rows = $this->select("");
			return $this->returnAllBeans($rows);
		}
		
		public function deleteRow($itemKey){
   	    	$itemKey = ValidateFields::validateNumericKey($itemKey);
   	    	if(isset($itemKey)){
				$this->delete("WHERE `orderNumber`=$itemKey");
   	    	}
		}
		
		public function insertRow($rowData){
			if(!empty($rowData)){
				foreach($rowData as &$value){
					$value = $this->quote($value);	
				}
				//$results['index'] = "LAST_INSERT_ID()"; //MAGIC:: Seems as if mysql does this...
				$insertString = $this->buildInsertSQL($rowData);
				$this->query($insertString);
			}
		}
		
		public function updateRow($rowData){
			$this->delete($rowData['key']);
			$this->insert($rowData);
		}


		public function getBean($orderData){	
			$order = new Order();
			$order->setClientName($orderData['clientName']);
			$order->setItemDescription($orderData['itemDescription']);
			$order->setPhone($orderData['clientPhone']);
			$order->setComment($orderData['comment']);
			$order->setClientEmail($orderData['clientEmail']);
			$order->setUnixKey($orderData['unixKey']);	
			$order->setDate($orderData['date']);	
			return $order;
		}
		
		public static function beanToArray($order){
			$orderData['clientName'] = $order->getClientName();
			$orderData['itemDescription'] = $order->getItemDescription();
			$orderData['clientPhone'] = $order->getPhone();
			$orderData['comment'] = $order->getComment();
			$orderData['clientEmail'] = $order->getClientEmail();
			$orderData['unixKey'] = $order->getUnixKey();
			return $orderData;
			
		}
	}
}
?>

