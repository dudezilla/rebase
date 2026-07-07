<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Captures REAL invocator-tag output: TestTagA is a self-contained recursive tag
 * that emits <<<TestTagA(n-1)>>> until a base case. Ground truth from the actual
 * class (not a model), plus the child tag the scanner finds in its output. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/syntax.php';
require __DIR__ . '/scan.php';
$E = dirname(__DIR__, 2);
require "$E/lib/TagLoader/Arguments/TagArguments.php";
require "$E/lib/Modules/taglib/Tag_Interface.php";
require "$E/invocators/tags/test_tags/TestTagA.php";

$out = [];
foreach ([0,1,2,3,5] as $n) {
    $call = "TestTagA($n)";
    $doc = (new TestTagA(TagArguments::argumentFactory($call)))->get_document();
    $out[] = ['call' => $call, 'n' => $n, 'document' => $doc,
              'children' => array_values(TagScanner::scan($doc))];
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
