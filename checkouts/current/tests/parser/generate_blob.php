<?php
/* Captures the content address (sha1 of JSON serialization) for a set of states. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/blobstore.php';
$bs = new BlobStore('/tmp/unused');   // hash() needs no filesystem
$states = [
  ['name'=>'Title','args'=>[]],
  ['name'=>'Body','args'=>['content']],
  ['name'=>'ItemList','args'=>['catalog','page','admin']],
  ['name'=>'Show','args'=>['']],
  ['name'=>'Login','args'=>['user','pass']],
  ['name'=>'Amp','args'=>['a&b','c<d']],
];
$out = [];
foreach ($states as $st) $out[] = ['state'=>$st, 'hash'=>$bs->hash($st)];
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
