<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/

session_start();
print("<br>Class Loader Harness<hr>\navailable classes: <br>");
require_once(CLASS_LOADER_HEADER);
$loader = getClassLoader();
print($loader->__toString());
DestroyClassLoader();
?>