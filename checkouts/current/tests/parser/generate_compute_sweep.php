<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Systematic computational-tag sweep: every binary op x operand pairs, computed
 * through the REAL parser + evaluator (TagComputer). Ground truth; each row is a
 * "<<<op(a)(b)>>> = result" data element. Zero denominators skipped (would throw). */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/compute.php';
$comp = new TagComputer();
$ops   = ['add','sub','mul','div','mod','max','min','lt','eq','pow'];
$pairs = [[1,1],[2,3],[3,2],[5,7],[7,5],[4,2],[9,3],[8,8],[12,5],[100,7],[6,4],[10,10]];
$out = [];
foreach ($ops as $op) {
    foreach ($pairs as [$a,$b]) {
        $tag = "<<<$op($a)($b)>>>";
        $out[] = ['tag' => $tag, 'expected' => $comp->compute($tag)];
    }
}
// unary ops over single operands
foreach (['inc','dec','abs'] as $op) {
    foreach ([1,5,9,42] as $a) {
        $tag = "<<<$op($a)>>>";
        $out[] = ['tag' => $tag, 'expected' => $comp->compute($tag)];
    }
}
// variadic (ternary) ops over triples
foreach (['add','mul','max','min'] as $op) {
    foreach ([[1,2,3],[3,1,2],[5,5,5],[2,4,6]] as [$a,$b,$c]) {
        $tag = "<<<$op($a)($b)($c)>>>";
        $out[] = ['tag' => $tag, 'expected' => $comp->compute($tag)];
    }
}
// variadic (4-operand) ops over quads
foreach (['add','mul','max','min'] as $op) {
    foreach ([[1,2,3,4],[4,3,2,1],[2,2,2,2],[5,1,4,2]] as [$a,$b,$c,$d]) {
        $tag = "<<<$op($a)($b)($c)($d)>>>";
        $out[] = ['tag' => $tag, 'expected' => $comp->compute($tag)];
    }
}
// 5-operand variadic ops
foreach (['add','mul','max','min'] as $op) {
    foreach ([[1,2,3,4,5],[5,4,3,2,1],[2,2,2,2,2]] as $set) {
        $tag = "<<<$op(" . implode(')(', $set) . ")>>>";
        $out[] = ['tag' => $tag, 'expected' => $comp->compute($tag)];
    }
}
// operand sets including zero (safe for add/mul/max/min, no division)
foreach (['add','mul','max','min'] as $op) {
    foreach ([[0,5],[5,0],[0,0],[0,3,7]] as $set) {
        $tag = "<<<$op(" . implode(')(', $set) . ")>>>";
        $out[] = ['tag' => $tag, 'expected' => $comp->compute($tag)];
    }
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
