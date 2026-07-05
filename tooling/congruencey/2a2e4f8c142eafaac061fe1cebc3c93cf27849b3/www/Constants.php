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
define("MYSQL_SERVER",'localhost');
define("SERVER_LOGIN", 'db_uers');
define("SERVER_PASSWORD", 'xxxx');

define("MYSQL_AUTH_DATABASE", 'CONGRUENCY_USER');
define("AUTHENTICATOR_LOGIN",'authenticator');
define('AUTHENTICATOR_PASSWORD','auth995');

define("MYSQL_CONT_DATABASE", 'CONGRUENCY_DOCUMENT');
define("CONTENT_LOGIN",'content');
define('CONTENT_PASSWORD','cont995');

/* These were referenced throughout the store/order/form/auth modules but never
   defined here, so those subsystems fatal on construction (undefined constants
   are fatal on PHP 8). Placeholder credentials; set per install. */
define("MYSQL_STORE_DATABASE", 'CONGRUENCY_STORE');
define("STORE_LOGIN", 'store');
define('STORE_PASSWORD', 'store995');

define("MYSQL_ORDER_DATABASE", 'CONGRUENCY_ORDER');
define("ORDER_LOGIN", 'orderer');
define('ORDER_PASSWORD', 'order995');

define("MYSQL_FORM_DATABASE", 'CONGRUENCY_FORM');
define("FORM_LOGIN", 'forms');
define('FORM_PASSWORD', 'form995');

/*

MYSQL Login info.

*/

//define("SLASH", "\\");	//on win-dowz 
define("SLASH", "/");	//on 'nix systems
/*
SLASH refers to the slash between directories. The String "\\" will escape 
to the '\' character, which denotes a directory. C:\STUFF\file.php (Stuff is a
sub-directory within the C:\ Root directory.) 
So "C:\\STUFF\\file.php" would be a string that represents a path in php.

On a unix system '/' is the counterpart to '\', and '/' does not need to be escaped.
So: /STUFF/file.php is the unix equivellent to the example above.


	NOTE:	
		propper values for this are essential.
*/


//define("PLATFORMROOT", "c:".SLASH);	//win-dowz you may need to change the drive letter
define("PLATFORMROOT", SLASH);	//on 'nix

/*
	Windows the root of a drive is designated as a letter [a-z]:
	Note: 
		the slash \ (refers to the root directory and not the drive.	
	NOTE:	
		propper values for this are essential.


*/

define("ABS_PATH", PLATFORMROOT.'web'.SLASH.'web'.SLASH."congruency".SLASH);

//define("ABS_PATH", PLATFORMROOT.'web'.SLASH.'web'.SLASH.'congruency'.SLASH);
/*
The absolute path, you will need to change this.

Current value is equivallent to c:\\web\\current\\

You will need to set PLATFORMROOT and SLASH for your system, so start there, 
and come back once you understand those constants.

*/


define("TAGS_DIR", ABS_PATH.'invocators'.SLASH.'tags'.SLASH);
/*

Pages can make calls to embedded objects, here is the sub directory that will contain 
the class files. Relative to the root of the install.

*/


define("CONTENT_DIR", ABS_PATH.'content'.SLASH.'content'.SLASH);
/*

Pages can make calls to embedded objects, here is the sub directory that will contain 
the class files. Relative to the root of the install.

*/


/**********************************************************************************/
//Program constants.



define("WORKING_PAGE", 'WORKING_PAGE');
/*

WORKING_PAGE: 
refers to the Page object stored in the session. 
This constant is used to select the current page object amongst the possible session elements.
For example: $_SESSION[WORKING_PAGE] yields the current working page object.
	Note that the Page Object is likely unfinished.

*/

define("CLASS_LOADER_DIR",ABS_PATH."lib".SLASH."ClassLoader".SLASH);

define("CLASS_LOADER_HEADER",ABS_PATH."lib".SLASH."ClassLoader".SLASH."ClassLoaderHeader.php");
/*

CLASS_LOADER_HEADER

This is the absolute path to ClassLoader's Header. If you change the location of the ClassLoader you
will need to modify this constant. 

*/


define("ETC",ABS_PATH."etc".SLASH);   // referenced by UserPrivilegeSet (require_once ETC."Privilege.php") but previously undefined

define("LIB",ABS_PATH."lib");
/*

LIB

The library functions.
*/


define("BIN",ABS_PATH."bin".SLASH);
/*

BIN

Path to executable scripts.
*/


define("HARNESS",BIN . "Harness".SLASH);
/*

HARNESS

Path to test harnesses.
*/







define("KEY_PREFIX","<<<");	
define("KEY_SUFFIX",">>>");
define("TAG_KEY_PREFIX","/<<<");  
define("TAG_KEY_SUFFIX","\>>>/");
define("FUNCTION_NAME","[a-zA-Z]+");
define("FUNCTION_ARGUMENTS","(\([A-Za-z0-9]*\))*");
define("FUNCTION_ARGUMENT","/\([A-Za-z0-9]*\)/");
define("GET_TAG_IDENTIFIER","/([a-zA-Z0-9]+\s?(?=\(\s?[a-zA-Z0-9]*\s?\)))|(\s?[a-zA-Z0-9]+\s?)/");






/******************************************************************************

When  orders are processed, an email is sent. 
These constants concern that email.

*********************************************************************



*****************************************************************
This is the subject header for that email
****************************************************************/


define("ORDER_SUBJECT_HEADER","PAW: You have a new order");

/******************************************************************************
The recipiants of that email.
****************************************************************/
define("EMAIL_RECIPIANTS","steven.peterson@shaw.ca");



/**************************************************************
Conditional Logging.
***********************************************************/
define("USELOG_DEBUG",TRUE);
define("USELOG_DATABASE",TRUE);
define("USELOG_SECURITY",TRUE);

define("LOGUSER_SERVER", 'localhost');
define("LOGUSER_LOGIN", 'logUser');
define("LOGUSER_PASSWORD", 'logloglog');


?>
