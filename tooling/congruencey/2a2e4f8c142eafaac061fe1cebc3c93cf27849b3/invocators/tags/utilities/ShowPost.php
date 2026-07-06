<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
 if(!class_exists("ShowPost")){
	class ShowPost implements Tag_Interface{

		private $command;

		public function __construct($arguments){
	
		}
		
		public function get_document(){
		$result = '';	
		
			if(isset($_POST['loginform'])){
				$result = "<br>loginform data is in post<br>";		
			}else{
				$result = "<br>loginform data is NOT in post<br>";
			}
			
			
			print_r(array_values($_POST));
			
			return $result;
		}
	}	
}
?>
