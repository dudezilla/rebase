<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Ground-truth capture of the content scanner over embedded-tag documents. */
require __DIR__ . '/scan.php';
$docs = [
  "Hello <<<Title>>> world",
  "<<<Body(content)>>> and then <<<Logout>>>",
  "plain text with no tags at all",
  "<<<A>>><<<B>>><<<C>>>",
  "leading <<<ItemList(catalog)(page)(admin)>>> trailing",
  "digits-in-name <<<tag123>>> should not fully match name",
  "<<<Outer(<<<Inner>>>)>>>",
  "<<<Under_score(x_y)>>> ok",
  "<<<with space>>> not a tag",
  "line1 <<<One>>>\nline2 <<<Two(a)>>>\nline3",
  "<<<Empty()>>> and <<<Pair(a)(b)>>>",
  "malformed <<<Bad(>>> and good <<<Good>>>",
  "Price list <<<price_maker>>>. Buy now!",
  "<<<A>>>,<<<B>>>;<<<C>>>!",
  "list:\n- <<<One>>>\n- <<<Two>>>",
  "<<<Outer>>> contains <<<Inner(x)>>> and <<<Deep(a)(b)>>>",
  "tag at very end <<<End>>>",
  "<<<Start>>> tag at very start",
  "no close <<<Broken and trailing text",
  "double delims <<< <<<Real>>> >>>",
  "<<<With_Underscore(arg_val)>>> mixed content",
  "unicode caf\xc3\xa9 menu <<<Menu>>> here",
  "<<<Tab>>>\ttabbed then <<<After>>>",
  "adjacent <<<X>>><<<Y>>> no space",
  "\ttab before <<<Tabbed>>> tab after\t",
  "multi\nline\n<<<OnLine>>>\nmore",
  "quad <<<<Nested>>>>",
  "prefix<<<Glued>>>suffix",
  "<<<Alpha>>> mid <<<Beta>>> mid <<<Gamma>>> end",
  "empty-parens <<<Fn()>>> here",
  "under_only <<<___under___>>> ok",
  "colon:<<<After>>>;semicolon",
  "<<<One>>>\n\n\n<<<Two>>>\t\t<<<Three>>>",
  "punctuation!<<<Bang>>>?<<<Query>>>.",
];
// programmatic multi-tag documents: N interspersed tags (1..8) at known positions
for ($nTags = 1; $nTags <= 8; $nTags++) {
    $d = "para $nTags:";
    for ($i = 1; $i <= $nTags; $i++) $d .= " word <<<Item$i>>> more";  // note: no digit in name after 'Item'? 'Item1' has digit -> scanner rejects
    $docs[] = $d;
    // digit-free names so the scanner accepts all of them
    $d2 = "list:";
    $letters = 'abcdefgh';
    for ($i = 0; $i < $nTags; $i++) $d2 .= " <<<Tag" . strtoupper($letters[$i]) . ">>>,";
    $docs[] = $d2;
}
$out = [];
foreach ($docs as $d) $out[] = ['document' => $d, 'tags' => array_values(TagScanner::scan($d)), 'count' => count(TagScanner::scan($d))];
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
