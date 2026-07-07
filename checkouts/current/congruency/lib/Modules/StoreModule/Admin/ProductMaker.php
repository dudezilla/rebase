<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("ProductMaker")){
	class ProductMaker extends AbstractMaker{
	
		public function __construct(){
			$this->keyVariable = "productMaker";
			$this->formName = "PRODUCTMAKER";
			$this->selectionLink = "<a href='?page=productMakerLink'>Choose another product.</a>";
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
			$productDAO = new ProductDAO();
			$productBean = $productDAO->obtainRow($this->key);
			if(isset($productBean)){				
				$productArr['name'] = $productBean->getName();
				$productArr['description'] = $productBean->getDescription();
				$productArr['picture'] = $productBean->getPicture();
				$productArr['category'] =	$productBean->getCategory();
				$productArr['page'] = $productBean->getPage();
				$result = $productArr;
			}
			return $result;
		}
	}
}
?>