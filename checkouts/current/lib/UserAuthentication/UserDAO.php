<?php
/*
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
if(!class_exists("UserDAO")){
	class UserDAO{
		private $dataConnection;

		public function __construct(){			
			$this->dataConnection = DataConnection::CreateConnection(MYSQL_SERVER,MYSQL_AUTH_DATABASE,AUTHENTICATOR_LOGIN,AUTHENTICATOR_PASSWORD);
			$this->dataConnection->open();			
		}
			

		public function authenticateUser($login,$password){
			$login = DataConnection::quote($login);
			$password = DataConnection::quote($password);
			$statement = 
			"SELECT Module,Value FROM Group_Privileges 
			INNER JOIN User_Group_Mappings ON Group_Privileges.Group_ID = User_Group_Mappings.Group_ID 
			INNER JOIN Login_Password ON Login_Password.Login = User_Group_Mappings.Login
			WHERE Login_Password.Login = $login AND Password = $password";
			$resultSet = $this->dataConnection->query($statement);
			$privileges = $this->privileges($resultSet);
			return $privileges;
		}
		
		
		private function privileges($resultSet){
			$userPrivilege = NULL;
			if($resultSet){
				$rows=mysql_num_rows($resultSet);
				if($rows > 0){
					$userPrivilege = new UserPrivilegeSet();
					for($rows=mysql_num_rows($resultSet);$rows>0;$rows-- ){
						$permission = mysql_fetch_assoc($resultSet);
						$userPrivilege->add($permission['Module'],$permission['Value']); 
					}
				}
			}
			return $userPrivilege;
		}
		
	
		public function changePassword($login,$password){
			
		}
		
		public function addUser($login,$password,$userData){
		//Add user/password
		//Add user/group
		//Add user_information	
		}
	
	
		public function __sleep(){
			$this->dataConnection->close();
		}
	
		public function __wakeup(){
			$this->dataConnection->open();
		}
		
								
	}
}
?>