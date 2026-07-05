<?php
/* End-to-end pipeline capture: a tag string -> parsed {name,args} -> rendered to
 * XML/HTML/JSON/YAML. Records the whole composition so parser+renderer are
 * regression-locked together. Ground truth from the live code. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/syntax.php';
require __DIR__ . '/render.php';
$ENTRY = dirname(__DIR__, 2);
require "$ENTRY/lib/TagLoader/Arguments/TagArguments.php";
require "$ENTRY/lib/TagLoader/Parser/Tag_Parser.php";

$tags = [
  "<<<Title>>>","<<<Body(content)>>>","<<<Logout>>>",
  "<<<ItemList(catalog)(page)(admin)>>>","<<<Login(user)(pass)>>>",
  "<<<Order(cart)(user)(confirm)>>>","<<<Catalog(root)>>>",
  "<<<ShowPost(post_42)>>>","<<<Controls(admin)(edit)>>>","<<<price_maker(item_1)>>>",
  // more real invocator-tag names from the registry
  "<<<CategoryView(cat_5)>>>","<<<PriceChanger(item)(new_price)>>>","<<<ProductView(prod_9)>>>",
  "<<<ShowOrders(user)>>>","<<<Admin_Links(admin)>>>","<<<ToggleLogin(state)>>>",
  "<<<ConfigFormTag(step_1)>>>","<<<FormTag(form_a)>>>","<<<OrderFormTag(cart)>>>",
  "<<<OrdererTag(finalize)>>>","<<<BugReport>>>","<<<PriceMakerControl(x)>>>",
  // remaining real invocator tags (underscore-named + document + dev)
  "<<<Config_Form_Invocator(step)>>>","<<<Order_Form_Invocator(cart)>>>","<<<Orderer_Invocator(final)>>>",
  "<<<Catalog_Controller(root)>>>","<<<BugDemo(sqli)>>>","<<<TitleTag>>>",
  "<<<BodyTag>>>","<<<ContentTag(post_9)>>>",
];
$fmts = ['xml','html','json','yaml'];
$out = [];
foreach ($tags as $t) {
  $p = Tag_Parser::get_tag_parser($t);
  $state = ['name' => $p->get_function_name(),
            'args' => array_values($p->get_tag_arguments()->getArguments() ?? [])];
  $rendered = [];
  foreach ($fmts as $f) $rendered[$f] = TagStateRenderer::render($f, $state);
  $out[] = ['input' => $t, 'state' => $state, 'rendered' => $rendered];
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
