<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Regenerates parse-fixtures.json by running the real Tag_Parser over a corpus
 * of inputs and recording its exact output. Ground-truth capture, not guesswork.
 * Usage:  php generate_fixtures.php  > parse-fixtures.json  (run from tests/parser) */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/syntax.php';               // KEY_PREFIX/SUFFIX + regex constants (single source)
$ENTRY = dirname(__DIR__, 2);                  // the entry-folder root
require "$ENTRY/lib/TagLoader/Arguments/TagArguments.php";
require "$ENTRY/lib/TagLoader/Parser/Tag_Parser.php";

$inputs = [
  "<<<Foo>>>","<<<Title>>>","<<<Body>>>","<<<Content>>>","<<<Logout>>>","<<<x>>>","<<<Z9>>>",
  "<<<Foo(bar)>>>","<<<Body(content)>>>","<<<Login(user)>>>","<<<price_maker(item_1)>>>",
  "<<<Foo(bar)(baz)>>>","<<<Login(user)(pass)>>>","<<<pair(a)(b)>>>",
  "<<<ItemList(catalog)(page)(admin)>>>","<<<three(a)(b)(c)>>>",
  "<<<A(1)(2)(3)(4)(5)>>>","<<<many(a)(b)(c)(d)(e)(f)>>>",
  "<<<Show()>>>","<<<empties()()>>>","<<<mid(a)()(c)>>>",
  "<<<digits(123)(456)>>>","<<<under_score(one_two)(three_four)>>>",
  "<<<Mixed(a1)(b_2)(C3)>>>","<<<longNameHere(payload)>>>",
  "<<<tag_1(x)>>>","<<<UPPER(LOW)>>>","<<<n0(a)(b)(c)(d)>>>",
  "<<<numname9(a)>>>","<<<Cat(one)(two)(three)(four)(five)(six)(seven)>>>",
  "<<<mixCase(AbC)(dEf)>>>","<<<z(_leading)>>>","<<<trailer(end_)>>>",
  "<<<solo()>>>","<<<two_empty()()>>>","<<<w(a_b_c_d)>>>",
  "<<<Order(cart)(user)(confirm)>>>","<<<Catalog(root)>>>",
  "<<<n(0)>>>","<<<big(11)(22)(33)(44)>>>",
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
