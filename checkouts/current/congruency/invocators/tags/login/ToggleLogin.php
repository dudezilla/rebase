<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
 if(!class_exists("ToggleLogin")){
	class ToggleLogin implements Tag_Interface{

       	private $style;		
       	private $login_prompt;
       	private $logout_prompt;
       
		public function __construct($arguments){
			$this->login_prompt=$arguments->pop();
			$this->logout_prompt = $arguments->pop();
			$this->style = $arguments->pop();
		}
		
		public function get_document(){
			$return_value= "<a $this->style href='?page=login'>$this->login_prompt</a>";
			if(UserPrivilegeSet::logged_in()){
				$return_value= "<a $this->style href='?page=welcome'>ACCOUNT</a> | <a $this->style href='?page=logout'>$this->logout_prompt</a>";
			}
			return $return_value;		
		}
			 		
 	}
 }
?>
