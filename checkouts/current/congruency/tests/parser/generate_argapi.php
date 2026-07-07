<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Ground-truth capture of the TagArguments stack API: getArguments() (reversed
 * storage), top(), and the pop() drain sequence (recovers SOURCE order). */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/syntax.php';
require dirname(__DIR__, 2) . '/lib/TagLoader/Arguments/TagArguments.php';

$calls = ["Foo(a)(b)(c)","X(one)(two)","Bare","P(only)","E()","Multi(a)(b)(c)(d)(e)","Nums(1)(2)(3)",
  "Seven(a)(b)(c)(d)(e)(f)(g)","MidEmpty(a)()(c)","AllEmpty()()()","Under(a_b)(c_d)",
  "Two(x)(y)","One(z)","Big(1)(2)(3)(4)(5)(6)(7)(8)","Dup(a)(a)(a)","Mixed(a1)(b_2)(C3)"];
$out = [];
foreach ($calls as $call) {
    $args = TagArguments::argumentFactory($call)->getArguments();
    $top  = TagArguments::argumentFactory($call)->top();          // end() => false on empty
    $drain = TagArguments::argumentFactory($call);
    $seq = [];
    while ($drain->finished()) $seq[] = $drain->pop();
    $out[] = [
        "call"         => $call,
        "getArguments" => array_values($args ?? []),
        "top"          => $top,                                   // may be false
        "pop_sequence" => $seq,
    ];
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
