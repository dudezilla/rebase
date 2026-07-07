<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Parses a fixed input list under ONE tag-syntax constant variant (etc|www) and
 * emits [{input,name,argc}]. Run in a subprocess since PHP constants are
 * define-once. Pins the real etc-vs-www divergence (underscore acceptance). */
error_reporting(E_ALL & ~E_DEPRECATED);
$variant = $argv[1] ?? 'etc';
define("KEY_PREFIX", "<<<");
define("KEY_SUFFIX", ">>>");
if ($variant === 'etc') {                                  // etc/Constants.php: underscore allowed
    define("FUNCTION_ARGUMENT",  "/\\([A-Za-z0-9_]*\\)/");
    define("GET_TAG_IDENTIFIER", "/([a-zA-Z0-9_]+\\s?(?=\\(\\s?[a-zA-Z0-9_]*\\s?\\)))|(\\s?[a-zA-Z0-9_]+\\s?)/");
} else {                                                   // www/Constants.php: no underscore
    define("FUNCTION_ARGUMENT",  "/\\([A-Za-z0-9]*\\)/");
    define("GET_TAG_IDENTIFIER", "/([a-zA-Z0-9]+\\s?(?=\\(\\s?[a-zA-Z0-9]*\\s?\\)))|(\\s?[a-zA-Z0-9]+\\s?)/");
}
$E = dirname(__DIR__, 2);
require "$E/lib/TagLoader/Arguments/TagArguments.php";
require "$E/lib/TagLoader/Parser/Tag_Parser.php";
$inputs = [
  "<<<Foo(a_b)>>>","<<<und_name(x)>>>","<<<Foo(a)(b_c)>>>","<<<plain(abc)>>>",
  "<<<Num(1_2)>>>","<<<Foo(a1)>>>","<<<a_b_c(d)>>>",
  "<<<_lead(x)>>>","<<<trail_(y)>>>","<<<Mid_Name(a)(b)>>>","<<<Tag(x_y_z)>>>",
  "<<<UP_PER(A_B)>>>","<<<n(o_ne)(t_wo)>>>","<<<simple(abc)(def)>>>","<<<w9(a)(b_1)>>>",
];
$out = [];
foreach ($inputs as $in) {
  $p = Tag_Parser::get_tag_parser($in);
  $out[] = ["input"=>$in, "name"=>$p->get_function_name(),
            "argc"=>count($p->get_tag_arguments()->getArguments() ?? [])];
}
echo json_encode($out, JSON_UNESCAPED_SLASHES), "\n";
