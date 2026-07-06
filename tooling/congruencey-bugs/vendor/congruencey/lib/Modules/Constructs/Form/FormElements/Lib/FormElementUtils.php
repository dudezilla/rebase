<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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