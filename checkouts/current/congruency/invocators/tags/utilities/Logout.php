<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
 if(!class_exists("Logout")){
	class Logout implements Tag_Interface{



		public function __construct($arguments){
		}
		
		public function get_document(){
			if(UserPrivilegeSet::logged_in()){
				Redirect::commandFactory("logout");
				DestroySession::commandFactory("");		
			}
		}
	}	
}
?>
