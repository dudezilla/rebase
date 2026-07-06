<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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