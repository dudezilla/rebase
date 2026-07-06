<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* For every real registry tag name, embed <<<Name>>> in content and capture what
 * the scanner extracts. Confirms the real tag vocabulary is scannable (goal #2). */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/scan.php';
$reg = json_decode(file_get_contents(__DIR__ . '/tag-registry.json'), true);
$out = [];
foreach (array_keys($reg) as $name) {
    $doc  = "before <<<$name>>> after";
    $tags = array_values(TagScanner::scan($doc));
    $out[] = ['name' => $name, 'doc' => $doc, 'tags' => $tags, 'found' => ($tags === ["<<<$name>>>"])];
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
