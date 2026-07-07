<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
 if(!class_exists("Admin_Tag")){
	abstract class Admin_Tag implements Tag_Interface{

		protected $arguments;

		abstract protected function run_admin_function();

		
		public function get_document(){
			$return_value='';
			if(UserPrivilegeSet::logged_in()){
				if( UserPrivilegeSet::check_privilege(SKELETON_KEY) ){
					$return_value=$this->run_admin_function();				
				}
			}else{ //Should not be possible, but you never know.
				$return_value="<<<Logout()>>>";
			}				
			return $return_value;
		}

	}
 }
?>
