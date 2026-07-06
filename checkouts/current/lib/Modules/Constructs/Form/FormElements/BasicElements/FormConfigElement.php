<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!class_exists("FormConfigElement")){

	define("STYLE_REG","/<[sS]tyle\s?=\s?'.*?'\s?>/");
	define("ACTION_REG","/<[aA]ction\s?=\s?'.*?'\s?>/");
	define("ON_COMPLETE_REG","/<[oO]n\s?[cC]omplete\s?=\s?'.*?'\s?>/");
	define("INCOMPLETE_REG","/<[iI]ncomplete\s?=\s?'.*?'\s?>/");
	define("VALID_REG","/<[vV]alid\s?=\s?'.*?'\s?>/");
	define("INNER_EXPRESSION","/'.*(?='\s?>)/");

	class FormConfigElement extends AbstractFormElement{

		public function __construct(){
		}
		
		public function getHTML(){
		}		

		public function returnValue(){
		}
		
		public function getInitial(){
		}
		
		public function failsExtendedValidation(){
			return FALSE;
		}
		
		public function setFormObject($form){
			$form->setStyle(self::parseStyleTag($this->getElementString()));
			$form->setAction(self::parseActionTag($this->getElementString()));
			$form->setOnComplete(self::parseOnCompleteTag($this->getElementString()));
			$form->setIncomplete(self::parseIncompleteTag($this->getElementString()));
			$form->setValid(self::parseValidTag($this->getElementString()));
			$form->formConfigElement($this);
		}
		
		
		public static function parseValidTag($validTag){
			return self::parseReg($validTag,VALID_REG);
		}
		
		public static function parseIncompleteTag($incompleteTag){
			return self::parseReg($incompleteTag,INCOMPLETE_REG);
		}
				
		public static function parseStyleTag($styleTag){
			return self::parseReg($styleTag,STYLE_REG);
		}
		
		public static function parseActionTag($styleTag){
			return self::parseReg($styleTag,ACTION_REG);
		}
		
		public static function parseOnCompleteTag($onComplete){
			return self::parseReg($onComplete,ON_COMPLETE_REG);
		}
		
		public static function parseReg($string,$expression){
			$matches = array();
			if(preg_match($expression,$string,$matches)){
				$match = $matches[0];
				$refinedMatch= array();
				preg_match(INNER_EXPRESSION,$match,$refinedMatch);
				return substr($refinedMatch[0],1);
			}else{
				return NULL;	
			}
		}		
	}
}