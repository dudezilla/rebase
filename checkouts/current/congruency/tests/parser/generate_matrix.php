<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Cross-stage acceptance matrix: for each candidate tag string, capture whether
 * the content SCANNER matches it whole vs. what the PARSER extracts. Pins the
 * (deliberate) divergence between the two regexes — e.g. digits in a name are
 * scanned-rejected but parser-accepted. Ground truth from the live code. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/scan.php';
$E = dirname(__DIR__, 2);
require "$E/lib/TagLoader/Arguments/TagArguments.php";
require "$E/lib/TagLoader/Parser/Tag_Parser.php";

$inputs = [
  "<<<Foo>>>","<<<foo>>>","<<<Foo123>>>","<<<123>>>","<<<f_o_o>>>",
  "<<<Foo(a)>>>","<<<Foo(1)>>>","<<<Foo(a1)>>>","<<<Foo()>>>","<<<Foo(a)(b)>>>",
  "<<<Foo.Bar>>>","<<<Foo Bar>>>","<<<_x>>>","<<<X>>>","<<<mixedCase>>>",
  "<<<a(b)(c)(d)>>>","<<<UPPER(low)>>>","<<<n0(x)>>>","<<<tag_1(v)>>>","<<<9lives>>>",
  "<<<Foo9Bar>>>","<<<a1b2c3>>>","<<<___>>>","<<<Name(with_underscore)>>>","<<<CamelCase>>>",
  "<<<9>>>","<<<_>>>","<<<A.B.C>>>","<<<A B C>>>","<<<verylongtagnamehere>>>",
  "<<<Mix9_ed(a_1)(b2)>>>","<<<Under_Score_Name>>>","<<<x9>>>","<<<Tag(a)(b)(c)(d)(e)>>>","<<<UP_9(A_1)>>>",
];
$out = [];
foreach ($inputs as $in) {
  $scanned = TagScanner::scan($in);
  $p = Tag_Parser::get_tag_parser($in);
  $args = $p->get_tag_arguments()->getArguments() ?? [];
  $out[] = [
    "input"          => $in,
    "scanned_whole"  => ($scanned === [$in]),   // scanner matches the entire string as one tag
    "scan_count"     => count($scanned),
    "parser_name"    => $p->get_function_name(),
    "parser_argc"    => count($args),
  ];
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
