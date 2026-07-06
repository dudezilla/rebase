<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("PriceMaker")){
		class PriceMaker extends AbstractMaker{
	
		public function __construct(){
			$this->keyVariable = "priceMaker";
			$this->formName = "PRICEMAKER";
			$this->selectionLink = "<a href='?page=priceChange'>View all prices.</a>";
			$this->init();
		}
		
		protected function insertResultKeys($results){
			$results['key'] = $this->key;
			return $results;	
		}
		
		protected function useValidator($prospect){
			return ValidateFields::validateNumericKey($prospect);
		}
		
		protected function initFormArray(){
			$result = NULL;
			$priceDAO = new PriceDAO();
			$result = $priceDAO->get_price($this->key);
			return $result;
		}
	}
}
?>