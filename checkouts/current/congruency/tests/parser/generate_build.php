<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Ground truth for build->parse: builds tags from (name, source args) and records
 * the built string + what the real parser extracts (name + reversed args). */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/builder.php';
$E = dirname(__DIR__, 2);
require "$E/lib/TagLoader/Arguments/TagArguments.php";
require "$E/lib/TagLoader/Parser/Tag_Parser.php";

$specs = [
  ['A', []], ['Foo', ['a']], ['Item', ['a','b']], ['Login', ['user','pass']],
  ['Pair', ['x','y','z']], ['Nums', ['1','2','3']], ['Und', ['a_b','c_d']],
  ['Solo', ['only']], ['Five', ['a','b','c','d','e']],
];
// programmatic sweep: names x arg-count 0..8
foreach (['A','Foo','Item','Bee','Under_Score','Xy'] as $nm) {
    for ($k = 0; $k <= 8; $k++) {
        $src = [];
        for ($i = 0; $i < $k; $i++) $src[] = "w$i";
        $specs[] = [$nm, $src];
    }
}
$out = [];
foreach ($specs as [$name, $src]) {
    $tag = TagBuilder::build($name, $src);
    $p = Tag_Parser::get_tag_parser($tag);
    $out[] = [
        'name' => $name, 'source_args' => $src, 'tag' => $tag,
        'parsed_name' => $p->get_function_name(),
        'parsed_args' => array_values($p->get_tag_arguments()->getArguments() ?? []),
    ];
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
