<?php
/*
Congruency The web management system.
Copyright (C) 2006 Steven Peterson

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

<<<Contact Info>>>
Steven Peterson,2234 4th Ave NW, Calgary, AB T2N 0N7 Canada. steven.peterson@shaw.ca
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