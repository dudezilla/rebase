<?php
/*
 * Created on Feb 3, 2007
 *
 * 
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
