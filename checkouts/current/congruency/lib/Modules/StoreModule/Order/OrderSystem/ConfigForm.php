<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("ConfigForm")){
	/*
		Build the Item description object in a form, then use the form action to link to 
		the order form....
		
		This would look like:
		
		
			Config form intro:
		______________________________________________________________
		
			Base Price XXX.xx
			
			Option1 A B C D
			
			Option2 A B C D
			
			Opiton3 A B C D
			
		______________________________________________________________
			
			Complete Options for price.	
		
		{Submit]

XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX


			Config form intro:
		______________________________________________________________
		
			Base Price XXX.xx
			
			Option1 A 
			
			Option2 B
			
			Opiton3 C
			
		______________________________________________________________
			
			Item configured with A,B,C price: Base+Option1(A)+Option2(B)+Option3(C).	

		{continue]
		
	
	*/

	define("ESTIMATE_HEADER","Estimate:<hr>\n");
	define("INITIAL_ESTIMATE_STRING","Please complete the form to obtain an estimate of the price.<br>\n");


	class ConfigForm{
		private static $configForm;
		private $price;
		private $description;
		private $queued;			//Toss element strings into here.
		private $formKey;
		private $formManager;		

		private function __construct($key){
			$this->formKey = "ConfigForm-" . $key;
			$this->formManager = PersistentObjectManager::getData("FORM_MANAGER");
		}
	
		public static function obtainPrice($subject){
			preg_match("/##\s?[Pp]rice\s?=\s?[+-]?[0-9]+(\.[0-9][0-9])?\s?##/",$subject,$priceArray);
			if($priceArray){
				preg_match("/[+-]?[0-9]+(\.[0-9][0-9])?/",$priceArray[0],$priceArray);
				return $priceArray[0];
			}
		}

		public static function obtainDescription($subject){
			preg_match("/##\s?[Dd]escription\s?=.*?##/",$subject,$descriptionArray);
			if($descriptionArray){
				preg_match("/=.*?##/",$descriptionArray[0],$descriptionArray);
				return substr($descriptionArray[0],1,strlen($descriptionArray[0]) - 3);	
			}
		}	
	
		public static function launchConfigForm($productId){
			if (!isset(self::$configForm)) {
				self::$configForm = new ConfigForm($productId);
			}
			return self::$configForm->__toString();
		}

		public static function getObjectReference(){
			return self::$configForm;
		}

		private function calculateEstimate(){
			$estimate = INITIAL_ESTIMATE_STRING;
			if(!empty($this->queued)){
				foreach ($this->queued as $adjustment){
					$this->price += ConfigForm::obtainPrice($adjustment);
					$this->description .= "<tr><td align='left' width='70%'>" . ConfigForm::obtainDescription($adjustment) . "</td><td align=right>$".ConfigForm::obtainPrice($adjustment) ."</td></tr>";
				}
				$estimate = "<br>\n<b>Item configuration\n</b>\n<table cellspacing='0'>\n" . $this->description ;
				$estimate .= "<tr bgcolor='#FFDDFF'><td><b>Item Price:</b></td><td align='right'> Canadian $" . $this->price ."<br>\n US Dollars $" . round($this->price * 0.95,2)
				. "</td></tr></table>\n";

				PersistentObjectManager::setData('PRODUCT_DESCRIPTION',$estimate); 
			}
			return ESTIMATE_HEADER . $estimate;
		}

		public function __toString(){
			$formString = $this->formManager->runForm($this->formKey);
			if($formString!=NULL){ 
				$formString = $this->calculateEstimate() . $formString;
			}else{
				$formString = "<!--Config Form Error-->";	
			}
			return $formString;
		}
		
		public static function enqueue($elementString,$elementId){
			if(isset(self::$configForm)){
				self::$configForm->pushString($elementString,$elementId);
			}
		}

		private function pushString($elementString,$elementId){
			$this->queued[$elementId] = $elementString;		
		}

		public function __clone(){
			trigger_error('Attempt to clone singleton type.', E_USER_ERROR);
		}
	}
}
?>