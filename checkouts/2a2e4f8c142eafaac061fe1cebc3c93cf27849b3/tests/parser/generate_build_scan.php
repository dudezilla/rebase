<?php
/* Inbound pipeline capture: build a tag from a spec, embed it in content, scan it
 * back out, and parse it. Ties TagBuilder -> TagScanner -> Tag_Parser end to end. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/builder.php';
require __DIR__ . '/scan.php';
$E = dirname(__DIR__, 2);
require "$E/lib/TagLoader/Arguments/TagArguments.php";
require "$E/lib/TagLoader/Parser/Tag_Parser.php";

$specs = [
  ['A', []], ['Foo', ['a']], ['Item', ['a','b']], ['Login', ['user','pass']],
  ['Under_score', ['x']], ['Cat', ['c','d','e']], ['Solo', ['only']],
  ['mixCase', ['w','w2']], ['Order', ['cart','confirm']], ['Show', ['']],
  ['Empty', []], ['Wide', ['a','b','c','d','e','f']], ['One', ['single']],
  ['Nums', ['1','22','333']], ['Deep_Under', ['a_b','c_d','e_f']], ['Z', ['q']],
  ['Login', ['user','pass']], ['Catalog', ['root']], ['Logout', []],
];
$out = [];
foreach ($specs as [$name, $src]) {
    $tag = TagBuilder::build($name, $src);
    $doc = "content start $tag content end";
    $scanned = array_values(TagScanner::scan($doc));
    $parsed  = array_map(function($inv){
        $p = Tag_Parser::get_tag_parser($inv);
        return ['name' => $p->get_function_name(),
                'args' => array_values($p->get_tag_arguments()->getArguments() ?? [])];
    }, $scanned);
    $out[] = ['name'=>$name, 'source_args'=>$src, 'tag'=>$tag, 'doc'=>$doc,
              'scanned'=>$scanned, 'parsed'=>$parsed];
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
