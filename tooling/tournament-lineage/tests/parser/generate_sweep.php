<?php
/* Broad deterministic parse sweep: names x arg-count x arg-token-style. Captured
 * ground truth; kind "parse" so Suite A covers it via the manifest. Deterministic
 * (index-driven), no randomness. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/syntax.php';
$E = dirname(__DIR__, 2);
require "$E/lib/TagLoader/Arguments/TagArguments.php";
require "$E/lib/TagLoader/Parser/Tag_Parser.php";

$names  = ['A','Bee','c_d','Item','xY'];
$styles = [
  'word' => fn($i) => 'w' . $i,
  'num'  => fn($i) => (string)($i * 7),
  'mix'  => fn($i) => 'm' . $i . '_' . ($i + 1),
];
$inputs = [];
foreach ($names as $nm) {
  foreach ([0,1,2,3] as $argc) {
    foreach ($styles as $sk => $fn) {
      if ($argc === 0 && $sk !== 'word') continue;          // no-arg case only once
      $args = '';
      for ($i = 0; $i < $argc; $i++) $args .= '(' . $fn($i) . ')';
      $inputs[] = "<<<$nm$args>>>";
    }
  }
}
$inputs = array_values(array_unique($inputs));
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
