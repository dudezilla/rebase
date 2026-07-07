<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/

if(!class_exists("UserPrivilegeSet")){
	require_once(ETC . "Privilege.php");
	class UserPrivilegeSet{
		private $privileges;
		
		//need to validate username and password.
		public static function authenticate($login,$password){
			$userDAO = new UserDAO();
			$userPrivileges = $userDAO->authenticateUser($login,$password);
			if(isset($userPrivileges)){
				PersistentObjectManager::setData("USER_ID",$userPrivileges);	
			}			
		}
		
		public static function logged_in(){
			$userData = PersistentObjectManager::getData("USER_ID");
			return ($userData!=NULL);
		}
			
		
		public function __construct(){	
			$this->privileges = array();
		}
		
		
		public function add($module,$value){
			if(isset($module)){
				$this->privileges[$module]=$value;
			}
		}
		
		public function __toString(){
			$rightsTable = "<table width =30% align='center'>";
			if(!empty($this->privileges)){
					$rightsTable .='<tr><td width=50%><b>Privilege</b></td><td width=50%><b>Value</b></td></tr>';
				foreach($this->privileges as $right=>$value){
					$rightsTable .= "<tr><td>$right</td><td>$value</td></tr>";
				}
			}
			$rightsTable .= '</table>';
			return $rightsTable;
		}
		
		
		public static function check_privilege($privilege){
			$privilege_set= PersistentObjectManager::getData("USER_ID");
			$privilege_data = $privilege_set->privileges;
			return isset($privilege_data[SKELETON_KEY]) || isset($privilege_data[$privilege]);
		}
				
	}	
}
?>
