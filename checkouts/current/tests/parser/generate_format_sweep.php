<?php
/* Systematic render sweep: states across arg-counts 0..8 rendered to all four
 * formats + round-trip flag. Ground truth (goal #1), reproducibility-locked. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/render.php';
$fmts = ['xml','html','json','yaml'];
$out = [];
for ($k = 0; $k <= 12; $k++) {
    $args = [];
    for ($i = 0; $i < $k; $i++) $args[] = "arg$i";
    $state = ['name' => "S$k", 'args' => $args];
    $row = ['state' => $state, 'rendered' => [], 'roundtrips' => []];
    foreach ($fmts as $f) {
        $r = TagStateRenderer::render($f, $state);
        $row['rendered'][$f]   = $r;
        $row['roundtrips'][$f] = (TagStateRenderer::parse($f, $r) === $state);
    }
    $out[] = $row;
}
// second dimension: special-character args at each arg-count (escaping x sweep)
$specials = ['a&b', 'c<d>', 'q"z', "p'r", '<<<t>>>', 'x & y'];
for ($k = 1; $k <= 6; $k++) {
    $state = ['name' => "SP$k", 'args' => array_slice($specials, 0, $k)];
    $row = ['state' => $state, 'rendered' => [], 'roundtrips' => []];
    foreach ($fmts as $f) {
        $r = TagStateRenderer::render($f, $state);
        $row['rendered'][$f]   = $r;
        $row['roundtrips'][$f] = (TagStateRenderer::parse($f, $r) === $state);
    }
    $out[] = $row;
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
