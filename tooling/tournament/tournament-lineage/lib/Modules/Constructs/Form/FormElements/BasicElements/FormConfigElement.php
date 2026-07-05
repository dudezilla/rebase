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