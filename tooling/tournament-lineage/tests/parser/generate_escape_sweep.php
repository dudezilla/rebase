<?php
/* Escape stress-sweep: states with N special-character args (increasing counts)
 * rendered to all four formats + round-trip flag. Combinatorial escaping coverage,
 * ground truth (goal #1). */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/render.php';
$specials = ['a&b', 'c<d>', 'q"z', "p'r", '<<<t>>>', 'x & y < z', '&amp;lit', '"<>&\'', 'a<<b>>c', 'tab\there'];
$fmts = ['xml','html','json','yaml'];
$out = [];
for ($k = 1; $k <= 10; $k++) {
    $args = array_slice($specials, 0, $k);
    $state = ['name' => "E$k", 'args' => $args];
    $row = ['state' => $state, 'rendered' => [], 'roundtrips' => []];
    foreach ($fmts as $f) {
        $r = TagStateRenderer::render($f, $state);
        $row['rendered'][$f]   = $r;
        $row['roundtrips'][$f] = (TagStateRenderer::parse($f, $r) === $state);
    }
    $out[] = $row;
}
// a few states with special names too
foreach ([['A&B',['ok']], ['q"n',['a&b']], ['<x>',['<<<y>>>']]] as [$nm,$ar]) {
    $state = ['name'=>$nm,'args'=>$ar];
    $row = ['state'=>$state,'rendered'=>[],'roundtrips'=>[]];
    foreach ($fmts as $f) { $r = TagStateRenderer::render($f,$state); $row['rendered'][$f]=$r; $row['roundtrips'][$f]=(TagStateRenderer::parse($f,$r)===$state); }
    $out[] = $row;
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
