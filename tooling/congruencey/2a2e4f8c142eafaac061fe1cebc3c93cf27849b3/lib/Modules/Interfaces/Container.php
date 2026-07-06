<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
if(!interface_exists("Container")){
	interface Container{
		public static function get_container();
		public static function add($value,$reference);
		public static function remove($value);
	}	
}
?>
