<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
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


