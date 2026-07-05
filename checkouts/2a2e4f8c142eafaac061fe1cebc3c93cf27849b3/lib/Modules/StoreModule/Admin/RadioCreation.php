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
if(!class_exists("RadioCreation")){
	class RadioCreation{

		private function __construct(){}
		
		/*
		    ...
			While obtaining the form result: the contents from all the text fields and text areas
			needs to be converted into a single string, known as the elementString. ...
			
			
		*/
		public static function getElementString($postData,$numberOfRadios){
			$elementString = NULL;
			for($counter=0;$counter<$numberOfRadios;$counter++){
				if(isset($postData['Price_'.$counter])){
					$price=$postData['Price_'.$counter];			
				}else{
					$price=NULL;
				}
				if(isset($postData['Description_'.$counter])){
					$description=$postData['Description_'.$counter];			
				}else{
					$description=NULL;
				}
				$elementString .= self::radioControlString($price,$description);				
			}
			return $elementString;
		}
		
		private static function radioControlString($price,$description){	
			return "<<## Price=$price ## Description=$description ## >>";
		}

		public static function parseRadioElements($radioElements){
			$radioArray= NULL;
			if(isset($radioElements)){
				$counter=0;
				foreach($radioElements as $radio){
					$price = ConfigForm::obtainPrice($radio);
					$description = ConfigForm::obtainDescription($radio);
					$radioArray[$counter++]= array('price'=>$price,'description'=>$description);
				}
			}
			return $radioArray;	
		}

		public static function getRadioString($numberOfRadios,$radioElements){
			$counter = 0;
			$tabIndex = 1;
			$radioArray = self::parseRadioElements($radioElements);
			$radioString = NULL;
			for($counter=0; $counter < $numberOfRadios; $counter++){
				$price = NULL;
				$description = NULL;
				if(isset($radioArray[$counter])){
					$price = $radioArray[$counter]['price'];
					$description = $radioArray[$counter]['description'];
				}
				$radioString .= self::getTextBoxes($counter,$tabIndex,$price,$description);
			}
			$radioString .= "<br>Number of Radios: &nbsp;<input type='text' name='numberOfRadios' value='$numberOfRadios'>";
			return $radioString;	
		}

		private static function getTextBoxes($pairNumber,&$tabIndex,$price,$description){
			return
			"<br>\nPrice: <input type='text' value='$price' name='Price_$pairNumber' tabindex='".$tabIndex++."'><br>" .
			"Description: <br><textArea cols='50' name='Description_$pairNumber' tabindex='".$tabIndex++."'>$description</textArea><br>";	
		}
	}
}
?>