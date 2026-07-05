<?php
/* Capture renderer escaping + round-trip behavior for states containing special
 * characters (< > & " ' and tag-like text). Ground truth: records each format's
 * rendered output AND whether render->parse recovers the original state, so the
 * corpus pins escaping behavior and documents round-trip limits honestly. */
require __DIR__ . '/render.php';
$states = [
  ['name'=>'Tag', 'args'=>['a<b']],
  ['name'=>'Tag', 'args'=>['x&y']],
  ['name'=>'Tag', 'args'=>['q"z']],
  ['name'=>"Tag", 'args'=>["p'q"]],
  ['name'=>'Tag', 'args'=>['<<<nested>>>']],
  ['name'=>'Tag', 'args'=>['a<b & c>d']],
  ['name'=>'Amp&Co', 'args'=>['ok']],
  ['name'=>'Tag', 'args'=>['line1','a&b','c<d']],
  // special characters in the NAME (attribute-escaping paths)
  ['name'=>'Na"me', 'args'=>['x']],
  ['name'=>'a<b>',  'args'=>['y']],
  ["name"=>"O'Brien", 'args'=>['z']],
  ['name'=>'A&B&C', 'args'=>[]],
  ['name'=>'<<<x>>>', 'args'=>['q']],
  ['name'=>'q"u<o>t&e', 'args'=>['a','b']],
];
$fmts = ['xml','html','json','yaml'];
$out = [];
foreach ($states as $st) {
  $row = ['state'=>$st, 'rendered'=>[], 'roundtrips'=>[]];
  foreach ($fmts as $f) {
    $r = TagStateRenderer::render($f, $st);
    $row['rendered'][$f]   = $r;
    $row['roundtrips'][$f] = (TagStateRenderer::parse($f, $r) === $st);
  }
  $out[] = $row;
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
