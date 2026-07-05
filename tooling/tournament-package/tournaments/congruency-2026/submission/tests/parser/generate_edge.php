<?php
/* Captures ground-truth parser behavior for EDGE / MALFORMED inputs into
 * edge-fixtures.json. Documents exactly what the (dated) parser does today so
 * any future simplification is regression-checked against reality. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/syntax.php';
$ENTRY = dirname(__DIR__, 2);
require "$ENTRY/lib/TagLoader/Arguments/TagArguments.php";
require "$ENTRY/lib/TagLoader/Parser/Tag_Parser.php";

$inputs = [
  "<<<>>>",                 // empty body
  "<<<   >>>",              // whitespace body
  "<<<Foo(bar>>>",          // unbalanced open paren
  "<<<Foo)bar(>>>",         // inverted parens
  "<<<Foo(a b)>>>",         // space inside arg (regex rejects)
  "<<<Foo(a-b)>>>",         // hyphen inside arg
  "<<<Foo(a)(b)tail>>>",    // trailing junk after args
  "<<<foo.bar(x)>>>",       // dot in name
  "<<<A(a)(a)(a)>>>",       // duplicate args
  "<<<123(x)>>>",           // numeric-leading name
  "<<<_priv(x)>>>",         // leading underscore name
  "<<<Nested(<<<Inner>>>)>>>", // nested-looking body
  "<<<Tag(a,b)>>>",         // comma inside arg
  "<<<Spaces( x )>>>",      // padded arg
  "<<<>>>>",                // extra suffix char
  "<<<<Foo>>>",             // extra prefix char
  "<<<Foo>>",               // short suffix
  "<<Foo>>>",               // short prefix
  "<<<A(b)(c)(d)(e)(f)(g)>>>", // many args
  "<<<MiXeD_CaSe_9(Arg_1)>>>", // mixed name with digits+underscore
  "<<<(noname)>>>",         // body starts with a paren
  "<<<Foo()()()>>>",        // three empty args
  "<<<Bar\n(baz)>>>",       // newline inside tag
  "<<<UPPER>>>",            // all-caps bare name
];
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
