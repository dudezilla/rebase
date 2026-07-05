<?php
/* Parse grid: names x arg-token patterns, tags assembled via TagBuilder then
 * parsed. Ground truth; kind "parse" (Suite A). Combines name shapes and arg
 * token styles (word/digit/underscore/mixed/empty) not enumerated elsewhere. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/builder.php';
$E = dirname(__DIR__, 2);
require "$E/lib/TagLoader/Arguments/TagArguments.php";
require "$E/lib/TagLoader/Parser/Tag_Parser.php";

$names   = ['a','Foo','Item9','tag_1','X','mixCase','U_9','Z','name_9x','ABCdef','_lead','trail_'];
$argsets = [ [], ['a'], ['1'], ['a_b'], ['w','2','z_9'], ['',''], ['A1','b_2'], ['only'], ['0'], ['a','b','c','d'] ];
$inputs = [];
foreach ($names as $nm) foreach ($argsets as $set) $inputs[] = TagBuilder::build($nm, $set);
$inputs = array_values(array_unique($inputs));

$out = [];
foreach ($inputs as $in) {
    $p = Tag_Parser::get_tag_parser($in);
    $args = $p->get_tag_arguments()->getArguments() ?? [];
    $out[] = [
        'input'         => $in,
        'full_tag'      => $p->get_full_tag(),
        'function_name' => $p->get_function_name(),
        'arguments'     => array_values($args),
        'arg_count'     => count($args),
    ];
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
