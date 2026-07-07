<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if (!class_exists("HTMLFunctions")){
	class HTMLFunctions{
		private function HTMLFunctions(){}
		public static function link($linkText,$altText,$href){
			return "<a href='" . $href . "' alt='" . $altText . "'>" . $linkText . "</a>";
		}
		public static function image($imageLocation,$altText,$attributes){
			return "<img src='" . $imageLocation . "' alt='" . $altText . "' ".HTMLFunctions::array_contents($attributes) . " >";
		}
		private static function array_contents($array){
			$result = NULL;
			if(!empty($array)){
				foreach($array as $element){
					$result .= " ". $element;
				}
				return $result;
			}
		}
		
	}
}
?>