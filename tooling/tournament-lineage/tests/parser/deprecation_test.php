<?php
/* Modernization guard: constructing/initializing a Tag_Parser must NOT create a
 * dynamic property (PHP 8.2+ deprecation). Fails against un-modernized source. */
require __DIR__ . '/syntax.php';
$ENTRY = dirname(__DIR__, 2);
require "$ENTRY/lib/TagLoader/Arguments/TagArguments.php";
require "$ENTRY/lib/TagLoader/Parser/Tag_Parser.php";

$seen = [];
set_error_handler(function($no,$str) use (&$seen){ $seen[] = $str; return true; }, E_DEPRECATED);
Tag_Parser::get_tag_parser("<<<Foo(bar)(baz)>>>");
restore_error_handler();

$dynamic = array_filter($seen, fn($m) => str_contains($m, 'dynamic property'));
if ($dynamic) { echo "FAIL: dynamic-property deprecation still emitted:\n  ".implode("\n  ",$dynamic)."\n"; exit(1); }
echo "OK: no dynamic-property deprecation\n"; exit(0);
