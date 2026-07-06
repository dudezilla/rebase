<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Captures FACTS about the real BugReport tag's rendered output: the catalogued
 * bug IDs, severity distribution, output length, and the zero-JavaScript invariant
 * (goal #5) — a real invocator tag verified to emit no <script>. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/syntax.php';
$E = dirname(__DIR__, 2);
require "$E/lib/TagLoader/Arguments/TagArguments.php";
require "$E/lib/Modules/taglib/Tag_Interface.php";
require "$E/invocators/tags/dev/BugReport.php";

$doc = (new BugReport(TagArguments::argumentFactory("BugReport")))->get_document();
preg_match_all('/BUG-\d+/', $doc, $m);
$sev = [];
foreach (['critical','high','medium','low'] as $s) $sev[$s] = substr_count($doc, $s);
echo json_encode([
    'bug_ids'         => array_values(array_unique($m[0])),
    'severity_counts' => $sev,
    'length'          => strlen($doc),
    'has_script'      => (stripos($doc, '<script') !== false),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
