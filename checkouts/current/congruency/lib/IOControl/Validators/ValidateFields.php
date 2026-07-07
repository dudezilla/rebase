<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/

if(!class_exists("ValidateFields")){
		class ValidateFields{
		
		private function __construct(){}
		
		public static function validatePageKey($pageKey){
		//Match the page key to anything entirely composed of 2 or more letters.		
			$result = array();
			preg_match("/[A-Za-z]{2,}/",$pageKey,$result);
			if($pageKey == $result[0]){
				return $result[0];
			}else{
				return NULL;
			}		
		}
		
		//Also needs to work with categoryKeys... Change the name?
		public static function validateItemKey($itemKey){
			return self::validateNumericKey($itemKey);
		}
		
		public static function validateNumericKey($itemKey){
			$result = array();
			preg_match("/[0-9]+/",$itemKey,$result);
			if($itemKey == $result[0]){
				return $result[0];
			}else{
				return NULL;
			}			
		}
		

		public static function validateFormKey($formKey){
			$result = array();
			preg_match("/[a-zA-Z0-9\-\_]+/",$formKey,$result);
			if($formKey == $result[0]){
				return $result[0];
			}else{
				return NULL;
			}		
		}
		
	}
}
?>