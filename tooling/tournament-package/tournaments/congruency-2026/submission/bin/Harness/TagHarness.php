<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
require_once(CLASS_LOADER_HEADER);
session_start();
getClassLoader();
require_once(BIN."Initialize_POM.php");
PersistentObjectManager::getPOM();
$document = new Document();
$document->setTemplate( "<html><head></head>Content Outline goes here-> <<<TestTagA(6)>>> <<<TestTagA(8)>>><body></body></html>" );
print($document->__toString());
PersistentObjectManager::pack($_SESSION['POM']);	
?>