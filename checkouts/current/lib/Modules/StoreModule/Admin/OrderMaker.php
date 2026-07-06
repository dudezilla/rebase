<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("OrderMaker")){
		class OrderMaker extends AbstractMaker{
	
		public function __construct(){
			$this->keyVariable = "orderMaker";
			$this->formName = "ORDERMAKER";
			$this->selectionLink = "<a href='?page=orderMakerLink'>Choose another order.</a>";
			$this->init();
		}
		
		protected function insertResultKeys(){
			$results['key'] = $this->key;
			return $results;	
		}
		
		protected function useValidator($prospect){
			return ValidateFields::validateNumericKey($prospect);
		}
		
		
		protected function initFormArray(){
			$result = NULL;
			$orderDAO = new OrderDAO();
			$orderBean = $orderDAO->obtainRow($this->key);
			if(isset($orderBean)){				
				$orderBean = $orderDAO->obtainRow($this->orderKey);
				$orderArr['clientName'] = $orderBean->getClientName();
				$orderArr['itemDescription'] = $orderBean->getItemDescription();
				$orderArr['clientPhone'] = $orderBean->getPhone();
				$orderArr['comment'] = $orderBean->getComment();
				$orderArr['clientEmail'] = $orderBean->getClientEmail();
				$orderArr['date'] = $orderBean->getDate();
				$orderArr['unixKey'] = $orderBean->getUnixKey();
				$orderArr['repComment'] = $orderBean->getRepComment();	
				$result = $orderBean;
			}
			return $result;
		}
	}
}
?>