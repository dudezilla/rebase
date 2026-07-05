<?php
/*
 * Created on Feb 23, 2007
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
 
//PURPOSE:
//Provides the opportunity to set the application at any given state prior to being loaded for the first time by the client.  
 
 
 


/* If a form manager is not in the POM, make sure the rest of the PO's are initialized. */
//Could use a marker for this purpose.
$formMan = PersistentObjectManager::getData("FORM_MANAGER");
if(!isset($formMan)){
	PersistentObjectManager::setData("FORM_MANAGER",new FormManager());
	/* Load the tag_def_loader */
	$tag_loader = PersistentObjectManager::getData("TAG_LOADER");
	if(!isset($tag_loader)){
		PersistentObjectManager::setData("TAG_LOADER", ClassLoader::LoaderFactory(TAGS_DIR));
	}
	/* Store Container*/
	Store::get_container();
}
?>


