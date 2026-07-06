<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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