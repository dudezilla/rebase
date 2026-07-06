<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Ground truth for the unified renderer with LIVE invocator-tag handlers: the real
 * TestTagA class is registered by name and expands recursively inside documents,
 * alongside templates and computational tags. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/render_document.php';
$E = dirname(__DIR__, 2);
require "$E/lib/Modules/taglib/Tag_Interface.php";
require "$E/invocators/tags/test_tags/TestTagA.php";

$templates = ['Greeting' => 'Hi', 'Name' => 'Sam'];
$handlers  = ['TestTagA' => fn($args, $inv) => (new TestTagA($args))->get_document()];
$r = new DocumentRenderer($templates, $handlers);

$docs = [
  "<<<TestTagA(3)>>>",
  "start <<<TestTagA(1)>>> end",
  "mix <<<add(2)(3)>>> and <<<TestTagA(0)>>>",
  "<<<Greeting>>>, <<<Name>>>! then <<<TestTagA(2)>>>",
  "two tags <<<TestTagA(1)>>> | <<<TestTagA(2)>>>",
  // conditional + sequence computation mixed with a live tag and templates
  "cond=<<<if(1)(100)(200)>>> greet=<<<Greeting>>> tag=<<<TestTagA(1)>>>",
  "seq=<<<seq(5)(6)(7)>>> name=<<<Name>>>",
  "branch <<<if(0)(1)(2)>>> then <<<TestTagA(0)>>>",
  "all: <<<Greeting>>> <<<add(4)(5)>>> <<<if(1)(9)(0)>>> <<<TestTagA(2)>>> <<<Name>>>",
];
$out = [];
foreach ($docs as $d) $out[] = ['document' => $d, 'expected' => $r->render($d)];
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
