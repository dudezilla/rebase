<?php
/*
 * Created on Jun 18, 2007
 *
 * 
 * Congruency The web management system.
 * 
 * Copyright (C) 2006 Steven Peterson

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
Steven Peterson,2234 4th Ave NW, Calgary, AB T2N 0N7 Canada. steven.peterson@rogers.com 
 */
 if(!class_exists("Login")){
	class Login implements Tag_Interface{

       
		public function __construct($arguments){
		}
		
		/*
		 * Three Conditions, logged in, not logged in, login failed(retry).
		 * 
		 * 
		 */
		
		public function get_document(){
			$return_value=NULL;
			if(UserPrivilegeSet::logged_in()){
			   	$return_value=$this->login_success();
			}else{
				
				//try removing the isset portion, see what happens.
				if(isset($_POST['login']) && $_POST['login'] != '' ){	
					$return_value = $this->authenticate();
				}else{
					$return_value = $this->login_form('');
				}
			}
			return $return_value;
		}


		private function authenticate(){
			$return_value = NULL;
			UserPrivilegeSet::authenticate($_POST['login'],$_POST['password']);
			if(UserPrivilegeSet::logged_in()){
			   	$this->login_success();
			}else{
				$return_value=$this->login_failed($_POST['login']);
			}
			return $return_value;							
		}


		private function login_success(){
			Redirect::commandFactory('welcome');
		}

		private function login_form($login){
			$returnValue = 			
			"\n<form method='POST'>\n".
			"<label for='login'>Login: </label>\n". 
			"<input type='text' name='login' value='$login'><br />\n".
			"<label for='password'>Password: </label>\n".
			"<input type='password' name='password'><br />\n".
			"<input type='submit' value='login'><br />\n".
			"</form>\n";
			return $returnValue;
		}
		
		private function login_failed($login){
			return $this->login_form($login) . "<br /><br />Your attempt to login as $login has failed.<br /><br />";
		}
	}
 }
?>
