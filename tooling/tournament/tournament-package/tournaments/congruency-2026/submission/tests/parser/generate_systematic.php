<?php
/* Systematic parse sweep: arg-count 0..8, name shapes, and whitespace variants.
 * Ground-truth capture; kind "parse" so Suite A picks it up via the manifest. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/syntax.php';
$E = dirname(__DIR__, 2);
require "$E/lib/TagLoader/Arguments/TagArguments.php";
require "$E/lib/TagLoader/Parser/Tag_Parser.php";

$inputs = [];
// arg-count sweep 0..8
for ($n = 0; $n <= 8; $n++) {
    $args = '';
    for ($i = 0; $i < $n; $i++) $args .= "(a$i)";
    $inputs[] = "<<<Sweep$args>>>";      // note: digit in name is fine for the PARSER
}
// name-shape variants
foreach (['A','ab','Abc','a_b','LongCamelCaseName','x1y2','_lead','trail_','U_P_P_E_R'] as $nm)
    $inputs[] = "<<<$nm(v)>>>";
// whitespace variants (exercise GET_TAG_IDENTIFIER \s? handling)
foreach (['<<<Foo >>>','<<< Foo>>>','<<<Foo (a)>>>','<<<Foo( a )>>>','<<<Foo(a) >>>'] as $w)
    $inputs[] = $w;
// high argument counts (parser stress at scale)
foreach ([10, 15, 20, 30, 40] as $n) {
    $args = '';
    for ($i = 0; $i < $n; $i++) $args .= "(x$i)";
    $inputs[] = "<<<Big$args>>>";
}
// long names (stress the identifier regex)
foreach ([str_repeat('a', 40), str_repeat('Z', 60), str_repeat('n_', 20)] as $nm)
    $inputs[] = "<<<$nm(v)>>>";

$out = [];
foreach ($inputs as $in) {
    $p = Tag_Parser::get_tag_parser($in);
    $args = $p->get_tag_arguments()->getArguments() ?? [];
    $out[] = [
        "input"         => $in,
        "full_tag"      => $p->get_full_tag(),
        "function_name" => $p->get_function_name(),
        "arguments"     => array_values($args),
        "arg_count"     => count($args),
    ];
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
