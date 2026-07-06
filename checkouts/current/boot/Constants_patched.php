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
if (!defined("MYSQL_SERVER")) define("MYSQL_SERVER",'localhost');
if (!defined("SERVER_LOGIN")) define("SERVER_LOGIN", 'db_uers');
if (!defined("SERVER_PASSWORD")) define("SERVER_PASSWORD", 'xxxx');

if (!defined("MYSQL_AUTH_DATABASE")) define("MYSQL_AUTH_DATABASE", 'CONGRUENCY_USER');
if (!defined("AUTHENTICATOR_LOGIN")) define("AUTHENTICATOR_LOGIN",'authenticator');
if (!defined('AUTHENTICATOR_PASSWORD')) define('AUTHENTICATOR_PASSWORD','auth995');

if (!defined("MYSQL_CONT_DATABASE")) define("MYSQL_CONT_DATABASE", 'CONGRUENCY_DOCUMENT');
if (!defined("CONTENT_LOGIN")) define("CONTENT_LOGIN",'content');
if (!defined('CONTENT_PASSWORD')) define('CONTENT_PASSWORD','cont995');

/* These were referenced throughout the store/order/form/auth modules but never
   defined here, so those subsystems fatal on construction (undefined constants
   are fatal on PHP 8). Placeholder credentials; set per install. */
if (!defined("MYSQL_STORE_DATABASE")) define("MYSQL_STORE_DATABASE", 'CONGRUENCY_STORE');
if (!defined("STORE_LOGIN")) define("STORE_LOGIN", 'store');
if (!defined('STORE_PASSWORD')) define('STORE_PASSWORD', 'store995');

if (!defined("MYSQL_ORDER_DATABASE")) define("MYSQL_ORDER_DATABASE", 'CONGRUENCY_ORDER');
if (!defined("ORDER_LOGIN")) define("ORDER_LOGIN", 'orderer');
if (!defined('ORDER_PASSWORD')) define('ORDER_PASSWORD', 'order995');

if (!defined("MYSQL_FORM_DATABASE")) define("MYSQL_FORM_DATABASE", 'CONGRUENCY_FORM');
if (!defined("FORM_LOGIN")) define("FORM_LOGIN", 'forms');
if (!defined('FORM_PASSWORD')) define('FORM_PASSWORD', 'form995');

/*

MYSQL Login info.

*/

//define("SLASH", "\\");	//on win-dowz 
if (!defined("SLASH")) define("SLASH", "/");	//on 'nix systems
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
if (!defined("PLATFORMROOT")) define("PLATFORMROOT", SLASH);	//on 'nix

/*
	Windows the root of a drive is designated as a letter [a-z]:
	Note: 
		the slash \ (refers to the root directory and not the drive.	
	NOTE:	
		propper values for this are essential.


*/

if (!defined("ABS_PATH")) define("ABS_PATH", str_replace(chr(92), "/", dirname(__DIR__)) . "/");

//define("ABS_PATH", PLATFORMROOT.'web'.SLASH.'web'.SLASH.'congruency'.SLASH);
/*
The absolute path, you will need to change this.

Current value is equivallent to c:\\web\\current\\

You will need to set PLATFORMROOT and SLASH for your system, so start there, 
and come back once you understand those constants.

*/


if (!defined("TAGS_DIR")) define("TAGS_DIR", ABS_PATH.'invocators'.SLASH.'tags'.SLASH);
/*

Pages can make calls to embedded objects, here is the sub directory that will contain 
the class files. Relative to the root of the install.

*/


if (!defined("CONTENT_DIR")) define("CONTENT_DIR", ABS_PATH.'content'.SLASH.'content'.SLASH);
/*

Pages can make calls to embedded objects, here is the sub directory that will contain 
the class files. Relative to the root of the install.

*/


/**********************************************************************************/
//Program constants.



if (!defined("WORKING_PAGE")) define("WORKING_PAGE", 'WORKING_PAGE');
/*

WORKING_PAGE: 
refers to the Page object stored in the session. 
This constant is used to select the current page object amongst the possible session elements.
For example: $_SESSION[WORKING_PAGE] yields the current working page object.
	Note that the Page Object is likely unfinished.

*/

if (!defined("CLASS_LOADER_DIR")) define("CLASS_LOADER_DIR",ABS_PATH."lib".SLASH."ClassLoader".SLASH);

if (!defined("CLASS_LOADER_HEADER")) define("CLASS_LOADER_HEADER",ABS_PATH."lib".SLASH."ClassLoader".SLASH."ClassLoaderHeader.php");
/*

CLASS_LOADER_HEADER

This is the absolute path to ClassLoader's Header. If you change the location of the ClassLoader you
will need to modify this constant. 

*/


if (!defined("ETC")) define("ETC",ABS_PATH."etc".SLASH);   // referenced by UserPrivilegeSet (require_once ETC."Privilege.php") but previously undefined

if (!defined("LIB")) define("LIB",ABS_PATH."lib");
/*

LIB

The library functions.
*/


if (!defined("BIN")) define("BIN",ABS_PATH."bin".SLASH);
/*

BIN

Path to executable scripts.
*/


if (!defined("HARNESS")) define("HARNESS",BIN . "Harness".SLASH);
/*

HARNESS

Path to test harnesses.
*/







if (!defined("KEY_PREFIX")) define("KEY_PREFIX","<<<");	
if (!defined("KEY_SUFFIX")) define("KEY_SUFFIX",">>>");
if (!defined("TAG_KEY_PREFIX")) define("TAG_KEY_PREFIX","/<<<");  
if (!defined("TAG_KEY_SUFFIX")) define("TAG_KEY_SUFFIX","\>>>/");
if (!defined("FUNCTION_NAME")) define("FUNCTION_NAME","[a-zA-Z]+");
if (!defined("FUNCTION_ARGUMENTS")) define("FUNCTION_ARGUMENTS","(\([A-Za-z0-9]*\))*");
if (!defined("FUNCTION_ARGUMENT")) define("FUNCTION_ARGUMENT","/\([A-Za-z0-9]*\)/");
if (!defined("GET_TAG_IDENTIFIER")) define("GET_TAG_IDENTIFIER","/([a-zA-Z0-9]+\s?(?=\(\s?[a-zA-Z0-9]*\s?\)))|(\s?[a-zA-Z0-9]+\s?)/");






/******************************************************************************

When  orders are processed, an email is sent. 
These constants concern that email.

*********************************************************************



*****************************************************************
This is the subject header for that email
****************************************************************/


if (!defined("ORDER_SUBJECT_HEADER")) define("ORDER_SUBJECT_HEADER","PAW: You have a new order");

/******************************************************************************
The recipiants of that email.
****************************************************************/
if (!defined("EMAIL_RECIPIANTS")) define("EMAIL_RECIPIANTS","steven.peterson@shaw.ca");



/**************************************************************
Conditional Logging.
***********************************************************/
if (!defined("USELOG_DEBUG")) define("USELOG_DEBUG",TRUE);
if (!defined("USELOG_DATABASE")) define("USELOG_DATABASE",TRUE);
if (!defined("USELOG_SECURITY")) define("USELOG_SECURITY",TRUE);

if (!defined("LOGUSER_SERVER")) define("LOGUSER_SERVER", 'localhost');
if (!defined("LOGUSER_LOGIN")) define("LOGUSER_LOGIN", 'logUser');
if (!defined("LOGUSER_PASSWORD")) define("LOGUSER_PASSWORD", 'logloglog');


?>
