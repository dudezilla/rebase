<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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
