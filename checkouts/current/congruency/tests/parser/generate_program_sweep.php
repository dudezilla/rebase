<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Parameterized recursive-program sweep: factorial, fibonacci, and sum across
 * input ranges, each result computed by the real evaluator (ground truth). */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/eval.php';
$ev = new TagEvaluator();
function n($v){ return ['op'=>'int','value'=>$v]; }
function vr($x){ return ['op'=>'var','name'=>$x]; }
function cl($f,...$a){ return ['op'=>'call','name'=>$f,'args'=>$a]; }
function op($o,...$a){ return ['op'=>$o,'args'=>$a]; }

$fact = ['fact'=>['params'=>['n'],'body'=>
  op('if', op('lt', vr('n'), n(1)), n(1),
    op('mul', vr('n'), cl('fact', op('sub', vr('n'), n(1)))))]];
$fib = ['fib'=>['params'=>['n'],'body'=>
  op('if', op('lt', vr('n'), n(2)), vr('n'),
    op('add', cl('fib', op('sub', vr('n'), n(1))), cl('fib', op('sub', vr('n'), n(2)))))]];
$sum = ['sum'=>['params'=>['n'],'body'=>
  op('if', op('eq', vr('n'), n(0)), n(0),
    op('add', vr('n'), cl('sum', op('sub', vr('n'), n(1)))))]];

$out = [];
foreach (range(0,10) as $k) { $p = ['defs'=>$fact,'main'=>cl('fact',n($k))]; $out[] = ['name'=>"fact-$k"] + $p + ['expected'=>$ev->run($p)]; }
foreach (range(0,20) as $k) { $p = ['defs'=>$fib, 'main'=>cl('fib', n($k))]; $out[] = ['name'=>"fib-$k"]  + $p + ['expected'=>$ev->run($p)]; }
foreach (range(0,15) as $k) { $p = ['defs'=>$sum, 'main'=>cl('sum', n($k))]; $out[] = ['name'=>"sum-$k"]  + $p + ['expected'=>$ev->run($p)]; }

$pow = ['pow'=>['params'=>['b','e'],'body'=>
  op('if', op('eq', vr('e'), n(0)), n(1),
    op('mul', vr('b'), cl('pow', vr('b'), op('sub', vr('e'), n(1)))))]];
foreach (range(0,10) as $e) { $p = ['defs'=>$pow, 'main'=>cl('pow', n(2), n($e))]; $out[] = ['name'=>"pow2-$e"] + $p + ['expected'=>$ev->run($p)]; }

$gcd = ['gcd'=>['params'=>['a','b'],'body'=>
  op('if', op('eq', vr('b'), n(0)), vr('a'),
    cl('gcd', vr('b'), op('mod', vr('a'), vr('b'))))]];
foreach ([[48,18],[54,24],[1071,462],[17,5],[100,75],[81,27],[13,7],[64,48],[45,15],[7,7]] as [$a,$b]) {
    $p = ['defs'=>$gcd, 'main'=>cl('gcd', n($a), n($b))];
    $out[] = ['name'=>"gcd-$a-$b"] + $p + ['expected'=>$ev->run($p)];
}

// triangular formula n*(n+1)/2 over a range
$tri = ['tri'=>['params'=>['n'],'body'=>op('div', op('mul', vr('n'), op('inc', vr('n'))), n(2))]];
foreach (range(0,15) as $k) { $p = ['defs'=>$tri, 'main'=>cl('tri', n($k))]; $out[] = ['name'=>"tri-$k"] + $p + ['expected'=>$ev->run($p)]; }

// fibonacci extended range (reuse $fib from above)
foreach (range(21,25) as $k) { $p = ['defs'=>$fib, 'main'=>cl('fib', n($k))]; $out[] = ['name'=>"fib-$k"] + $p + ['expected'=>$ev->run($p)]; }

// factorial + sum extended ranges (reuse $fact, $sum from above)
foreach (range(11,14) as $k) { $p = ['defs'=>$fact, 'main'=>cl('fact', n($k))]; $out[] = ['name'=>"fact-$k"] + $p + ['expected'=>$ev->run($p)]; }
foreach (range(16,25) as $k) { $p = ['defs'=>$sum, 'main'=>cl('sum', n($k))]; $out[] = ['name'=>"sum-$k"] + $p + ['expected'=>$ev->run($p)]; }

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
