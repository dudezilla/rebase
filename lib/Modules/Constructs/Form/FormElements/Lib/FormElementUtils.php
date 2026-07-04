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
if(!class_exists("FormElementUtils")){
	class FormElementUtils{	
	
		private function __construct(){}
		
		public static function parseRadioElementString($elementString){
			if(self::validateElementString($elementString)){
				$result = NULL;
				$working = split('>>',$elementString);
				$index = 0;
				$elements = count($working);
				unset($working[$elements - 1]); //For some reason an element is added to the end of the array I don't know why.
				foreach($working as $field){
			 		$result[$index++] = substr($field,2);
				}
				return $result;
			}else{
				print ("<!--Error: Failed parsing form element string. Form element may not appear-->");
		
			}
		}
		
		//I removed some code that kept track of the number of fields identified here.
		public static function validateElementString($elementString){
			$result = TRUE;
			$working = split('>>',$elementString);
			$index = 0;
			$elements = count($working);
			$workingResult = NULL;
			unset($working[$elements - 1]);
			foreach($working as $field){
				if( $result && strlen($field) > 1){
			 		if (0 == substr_compare($field,"<<",0,2)){
			 			$workingResult[$index++] = substr($field,2);
			 		}else{	
						//(A) String Fails validation. More '>>' than '<<'
						//		has form <<x>>*xxxx>>
						$result = FALSE;
			 		}
				}else{	
						//(B) String Fails validation. More '>>' than '<<'
						//		has form <<x>>*>+
						$result = FALSE;
				}
			}	
	
			return $result;
		}	
	}
}
?>