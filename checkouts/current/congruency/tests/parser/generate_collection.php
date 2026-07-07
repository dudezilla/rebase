<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Ground-truth capture of collection rendering + json/xml round-trip. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/collection.php';
$collections = [
  [ ['name'=>'Title','args'=>[]], ['name'=>'Body','args'=>['content']] ],
  [ ['name'=>'ItemList','args'=>['catalog','page']], ['name'=>'Login','args'=>['user','pass']], ['name'=>'Logout','args'=>[]] ],
  [ ['name'=>'Only','args'=>['x']] ],
  [],
  // larger + special-content collections
  [ ['name'=>'A','args'=>[]], ['name'=>'B','args'=>['1']], ['name'=>'C','args'=>['a','b']], ['name'=>'D','args'=>['x','y','z']], ['name'=>'E','args'=>[]] ],
  [ ['name'=>'Amp','args'=>['a&b']], ['name'=>'Lt','args'=>['c<d']], ['name'=>'Q','args'=>['q"z']] ],
  [ ['name'=>'Empties','args'=>['','','']] ],
  [ ['name'=>'Under','args'=>['a_b','c_d']], ['name'=>'Num','args'=>['10','20','30']] ],
  [ ['name'=>'Solo','args'=>['single']], ['name'=>'Solo','args'=>['single']] ],
  [ ['name'=>'Wide','args'=>['a','b','c','d','e','f','g']] ],
];
$fmts = ['xml','html','json','yaml'];
$out = [];
foreach ($collections as $states) {
  $row = ['states'=>$states, 'rendered'=>[], 'roundtrips'=>[]];
  foreach ($fmts as $f) $row['rendered'][$f] = CollectionRenderer::render($f, $states);
  foreach (['json','xml','html','yaml'] as $f) $row['roundtrips'][$f] = (CollectionRenderer::parse($f, $row['rendered'][$f]) === $states);
  $out[] = $row;
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
