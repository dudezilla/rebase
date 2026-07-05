<?php
/* Deterministic, self-contained oracle for the tag parser + 4-format renderer.
 * No DB, no server, no JavaScript. Exit 0 iff every assertion passes.
 *
 *   php run.php
 *
 * Suites:
 *   A) parse-fixtures.json  — Tag_Parser output matches captured ground truth
 *   B) format-fixtures.json — one canonical state renders identically-shaped
 *                             XML / HTML / JSON / YAML (format chosen at the edge)
 *   C) round-trip           — a parsed tag's state renders + JSON-reparses stably
 */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/syntax.php';
require __DIR__ . '/render.php';
$ENTRY = dirname(__DIR__, 2);
require "$ENTRY/lib/TagLoader/Arguments/TagArguments.php";
require "$ENTRY/lib/TagLoader/Parser/Tag_Parser.php";

$pass = 0; $fail = 0; $fails = [];
function check($cond, string $label) { global $pass, $fail, $fails;
    if ($cond) { $pass++; } else { $fail++; $fails[] = $label; } }

/* Parse a delimited tag into canonical state {name, args[]} via the real parser. */
function parse_state(string $tag): array {
    $p = Tag_Parser::get_tag_parser($tag);
    $args = $p->get_tag_arguments()->getArguments() ?? [];
    return ['name' => $p->get_function_name(), 'args' => array_values($args)];
}

// ---- Suite A: parser ground truth (self-configured from the manifest) ----
require __DIR__ . '/corpus.php';
$corpus = new Corpus();
$A = [];                                                   // gather ALL parse-kind datasets
foreach ($corpus->ofKind('parse') as $d) $A = array_merge($A, $corpus->load($d['id']));
foreach ($A as $c) {
    $p = Tag_Parser::get_tag_parser($c['input']);
    $args = array_values($p->get_tag_arguments()->getArguments() ?? []);
    check($p->get_function_name() === $c['function_name'], "A:name {$c['input']}");
    check($p->get_full_tag() === $c['full_tag'],           "A:full {$c['input']}");
    check($args === $c['arguments'],                       "A:args {$c['input']}");
    check(count($args) === $c['arg_count'],                "A:count {$c['input']}");
}

// ---- Suite B: one state -> four formats ----
$B = json_decode(file_get_contents(__DIR__ . '/format-fixtures.json'), true);
foreach ($B as $c) {
    foreach ($c['expected'] as $fmt => $want) {
        $got = TagStateRenderer::render($fmt, $c['state']);
        check($got === $want, "B:{$fmt} {$c['state']['name']}");
    }
}
// every format key present for every fixture (no format baked in / dropped)
$formats = array_keys(TagStateRenderer::renderers());
check($formats === ['xml','html','json','yaml'], "B:format-registry keys");

// ---- Suite C: parse -> render(json) -> reparse stability ----
foreach (['<<<Foo(bar)(baz)>>>','<<<ItemList(catalog)(page)(admin)>>>','<<<Title>>>'] as $tag) {
    $state = parse_state($tag);
    $json  = TagStateRenderer::render('json', $state);
    $back  = json_decode($json, true);
    check($back['tag'] === $state['name'] && $back['args'] === $state['args'],
          "C:roundtrip $tag");
}

// ---- Suite D: modernization — no dynamic-property deprecation on parse ----
$seenDep = [];
set_error_handler(function($no,$str) use (&$seenDep){ $seenDep[] = $str; return true; }, E_DEPRECATED);
Tag_Parser::get_tag_parser("<<<Foo(bar)(baz)>>>");
restore_error_handler();
check(!array_filter($seenDep, fn($m) => str_contains($m, 'dynamic property')),
      "D:no-dynamic-property-deprecation");

// ---- Suite F: edge / malformed inputs — captured ground-truth behavior ----
$F = json_decode(file_get_contents(__DIR__ . '/edge-fixtures.json'), true);
foreach ($F as $c) {
    $p = Tag_Parser::get_tag_parser($c['input']);
    $args = array_values($p->get_tag_arguments()->getArguments() ?? []);
    check($p->get_function_name() === $c['function_name'], "F:name {$c['input']}");
    check($p->get_full_tag() === $c['full_tag'],           "F:full {$c['input']}");
    check($args === $c['arguments'],                       "F:args {$c['input']}");
    check(count($args) === $c['arg_count'],                "F:count {$c['input']}");
}

// ---- Suite J: round-trip — state -> format -> state === state (all 4 edges) ----
$states = [];
foreach ($B as $c) $states[] = $c['state'];                       // format-fixture states
foreach (['<<<Login(user)(pass)>>>','<<<ItemList(catalog)(page)(admin)>>>',
          '<<<Title>>>','<<<Show()>>>','<<<price_maker(item_1)>>>'] as $t) $states[] = parse_state($t);
foreach ($states as $i => $st) {
    foreach (['xml','html','json','yaml'] as $fmt) {
        $round = TagStateRenderer::parse($fmt, TagStateRenderer::render($fmt, $st));
        check($round === $st, "J:roundtrip $fmt #$i {$st['name']}");
    }
}
check(array_keys(TagStateRenderer::parsers()) === ['xml','html','json','yaml'], "J:parser-registry keys");

// ---- Suite R: escaping + round-trip robustness (special characters) ----
$R = json_decode(file_get_contents(__DIR__ . '/escape-fixtures.json'), true);
foreach ($R as $c) {
    foreach ($c['rendered'] as $fmt => $want) {
        $got = TagStateRenderer::render($fmt, $c['state']);
        check($got === $want, "R:render $fmt " . json_encode($c['state']['args']));
        $rt = (TagStateRenderer::parse($fmt, $got) === $c['state']);
        check($rt === $c['roundtrips'][$fmt], "R:roundtrip $fmt " . json_encode($c['state']['args']));
    }
}

// ---- Suite BLD: TagBuilder — build a tag, parse recovers name + reversed args ----
require_once __DIR__ . '/builder.php';
$BLD = json_decode(file_get_contents(__DIR__ . '/build-fixtures.json'), true);
foreach ($BLD as $c) {
    $tag = TagBuilder::build($c['name'], $c['source_args']);
    check($tag === $c['tag'], "BLD:build {$c['name']}");
    $p = Tag_Parser::get_tag_parser($tag);
    check($p->get_function_name() === $c['parsed_name'], "BLD:parsed-name {$c['name']}");
    $pa = array_values($p->get_tag_arguments()->getArguments() ?? []);
    check($pa === $c['parsed_args'], "BLD:parsed-args {$c['name']}");
    check($pa === array_reverse($c['source_args']), "BLD:args-are-reversed {$c['name']}");
}

// ---- Suite ESW: escape stress-sweep — many special-char args, 4 formats ----
$ESW = json_decode(file_get_contents(__DIR__ . '/escape-sweep-fixtures.json'), true);
foreach ($ESW as $c) {
    foreach ($c['rendered'] as $fmt => $want) {
        check(TagStateRenderer::render($fmt, $c['state']) === $want, "ESW:render $fmt {$c['state']['name']}");
        $rt = (TagStateRenderer::parse($fmt, $want) === $c['state']);
        check($rt === $c['roundtrips'][$fmt], "ESW:roundtrip $fmt {$c['state']['name']}");
    }
}

// ---- Suite S: scanner<->parser acceptance matrix (syntax divergence) ----
require_once __DIR__ . '/scan.php';
$S = json_decode(file_get_contents(__DIR__ . '/matrix-fixtures.json'), true);
foreach ($S as $c) {
    $scanned = TagScanner::scan($c['input']);
    $p = Tag_Parser::get_tag_parser($c['input']);
    $argc = count($p->get_tag_arguments()->getArguments() ?? []);
    check(($scanned === [$c['input']]) === $c['scanned_whole'], "S:scan-whole {$c['input']}");
    check(count($scanned) === $c['scan_count'],                 "S:scan-count {$c['input']}");
    check($p->get_function_name() === $c['parser_name'],        "S:parser-name {$c['input']}");
    check($argc === $c['parser_argc'],                          "S:parser-argc {$c['input']}");
}
// the documented divergence actually exists: some inputs the parser names but the scanner drops
$divergent = array_filter($S, fn($c) => !$c['scanned_whole'] && $c['parser_name'] !== null);
check(count($divergent) >= 5, "S:divergence-present (digits/dots/spaces)");

// ---- Suite T: collection rendering — a document of many states, 4 formats ----
require_once __DIR__ . '/collection.php';
$T = json_decode(file_get_contents(__DIR__ . '/collection-fixtures.json'), true);
foreach ($T as $i => $c) {
    foreach ($c['rendered'] as $fmt => $want) {
        check(CollectionRenderer::render($fmt, $c['states']) === $want, "T:render $fmt #$i");
    }
    foreach (['json','xml','html','yaml'] as $fmt) {
        $rt = (CollectionRenderer::parse($fmt, $c['rendered'][$fmt]) === $c['states']);
        check($rt === $c['roundtrips'][$fmt], "T:roundtrip $fmt #$i");
    }
}
check(array_keys(CollectionRenderer::renderers()) === ['xml','html','json','yaml'], "T:collection-registry keys");

// ---- Suite FS: systematic format render sweep (arg-count 0..8, 4 formats) ----
$FS = json_decode(file_get_contents(__DIR__ . '/format-sweep-fixtures.json'), true);
foreach ($FS as $c) {
    foreach ($c['rendered'] as $fmt => $want) {
        check(TagStateRenderer::render($fmt, $c['state']) === $want, "FS:render $fmt {$c['state']['name']}");
        $rt = (TagStateRenderer::parse($fmt, $want) === $c['state']);
        check($rt === $c['roundtrips'][$fmt], "FS:roundtrip $fmt {$c['state']['name']}");
    }
}

// ---- Suite W: format interoperability — ingest any format, emit any other ----
$W = json_decode(file_get_contents(__DIR__ . '/interop-fixtures.json'), true);
$fmts4 = ['xml','html','json','yaml'];
foreach ($W as $st) {
    foreach ($fmts4 as $from) {
        $mid = TagStateRenderer::parse($from, TagStateRenderer::render($from, $st));   // ingest via $from
        foreach ($fmts4 as $to) {
            $final = TagStateRenderer::parse($to, TagStateRenderer::render($to, $mid)); // emit+reingest via $to
            check($final === $st, "W:$from->$to {$st['name']}");
        }
    }
}

// ---- Suite I: end-to-end pipeline — parse -> state -> 4-format render ----
$I = json_decode(file_get_contents(__DIR__ . '/integration-fixtures.json'), true);
foreach ($I as $c) {
    $state = parse_state($c['input']);
    check($state === $c['state'], "I:state {$c['input']}");
    foreach ($c['rendered'] as $fmt => $want) {
        check(TagStateRenderer::render($fmt, $state) === $want, "I:{$fmt} {$c['input']}");
    }
}

// ---- Suite V: TagArguments stack API — getArguments/top/pop drain ----
$V = json_decode(file_get_contents(__DIR__ . '/argapi-fixtures.json'), true);
foreach ($V as $c) {
    check(array_values(TagArguments::argumentFactory($c['call'])->getArguments() ?? []) === $c['getArguments'],
          "V:getArguments {$c['call']}");
    check(TagArguments::argumentFactory($c['call'])->top() === $c['top'], "V:top {$c['call']}");
    $drain = TagArguments::argumentFactory($c['call']);
    $seq = [];
    while ($drain->finished()) $seq[] = $drain->pop();
    check($seq === $c['pop_sequence'], "V:pop-drain {$c['call']}");
}

// ---- Suite E: tag registry — every key resolves through the dictionary ----
require __DIR__ . '/registry.php';
$reg = new TagRegistry();
$keys = $reg->keys();
check(count($keys) === 26, "E:registry-size(26)");
foreach ($keys as $id) {
    $d = $reg->resolve($id);
    check($reg->has($id), "E:has $id");
    check(isset($d['category'],$d['file'],$d['class']), "E:shape $id");
    check($d['class'] === $id, "E:class-id $id");
    check(is_file($ENTRY . '/' . $d['file']), "E:file-exists $id");
}
$threw = false;
try { $reg->resolve('NoSuchTag'); } catch (OutOfBoundsException $e) { $threw = true; }
check($threw, "E:unknown-throws (no magic-string fallthrough)");

// ---- Suite RI: category reverse-index — every member consistent with registry ----
$RI = json_decode(file_get_contents(__DIR__ . '/registry-index-fixtures.json'), true);
$riTotal = 0;
foreach ($RI as $cat => $members) {
    $riTotal += count($members);
    foreach ($members as $id) {
        check($reg->has($id), "RI:member-exists $id");
        check($reg->resolve($id)['category'] === $cat, "RI:member-category $id=$cat");
    }
}
check($riTotal === count($reg->keys()), "RI:covers-all-tags($riTotal)");

// ---- Suite RS: every real registry tag name is scannable in content ----
$RS = json_decode(file_get_contents(__DIR__ . '/registry-scan-fixtures.json'), true);
foreach ($RS as $c) {
    check(array_values(TagScanner::scan($c['doc'])) === $c['tags'], "RS:scan {$c['name']}");
    check(TagScanner::scan($c['doc']) === ["<<<{$c['name']}>>>"], "RS:found {$c['name']}");
}
check(count($RS) === count($reg->keys()), "RS:covers-all-tags");

// ---- Suite G: tag-driven evaluation core (keyed op dispatch, computed) ----
require __DIR__ . '/eval.php';
$ev = new TagEvaluator();
$G = json_decode(file_get_contents(__DIR__ . '/eval-fixtures.json'), true);
foreach ($G as $c) {
    check($ev->eval($c['expr']) === $c['expected'], "G:{$c['name']}");
}
$threwOp = false;
try { $ev->eval(['op'=>'bogus','args'=>[]]); } catch (OutOfBoundsException $e) { $threwOp = true; }
check($threwOp, "G:unknown-op-throws (dispatch is data, no silent case)");
check(in_array('if', $ev->opKeys(), true) && in_array('add', $ev->opKeys(), true), "G:op-registry");

// ---- Suite H: Turing-complete core — recursive named-function programs ----
$H = json_decode(file_get_contents(__DIR__ . '/eval-programs.json'), true);
foreach ($H as $c) {
    check($ev->run($c) === $c['expected'], "H:{$c['name']}");
}
$threwFn = false;
try { $ev->run(['defs'=>[], 'main'=>['op'=>'call','name'=>'ghost','args'=>[]]]); }
catch (OutOfBoundsException $e) { $threwFn = true; }
check($threwFn, "H:undefined-fn-throws");

// ---- Suite PS: parameterized program sweep (fact/fib/sum over ranges) ----
$PS = json_decode(file_get_contents(__DIR__ . '/program-sweep-fixtures.json'), true);
foreach ($PS as $c) {
    check($ev->run($c) === $c['expected'], "PS:{$c['name']}");
}
check(count($PS) === 104, "PS:instance-count(104)");

// ---- Suite Z: evaluator error contract — malformed programs raise mapped classes ----
$Z = json_decode(file_get_contents(__DIR__ . '/eval-errors-fixtures.json'), true);
foreach ($Z as $c) {
    $thrown = null;
    try {
        $c['kind'] === 'expr' ? $ev->eval($c['node']) : $ev->run($c['program']);
    } catch (\Throwable $e) { $thrown = get_class($e); }
    check($thrown === $c['error'], "Z:{$c['label']} -> {$c['error']}");
}

// ---- Suite L: server-side form chaining (goal #5, zero JS) ----
require __DIR__ . '/flow.php';
$flow = new FormFlow();
$L = json_decode(file_get_contents(__DIR__ . '/flow-fixtures.json'), true);
foreach ($L['single'] as $c) {
    check($flow->next($c['state'], $c['action']) === $c['next'], "L:step {$c['state']}--{$c['action']}");
}
foreach ($L['walks'] as $i => $c) {
    check($flow->walk($c['actions']) === $c['end'], "L:walk#$i -> {$c['end']}");
}
foreach ($L['invalid'] as $c) {
    $threw = false;
    try { $flow->next($c['state'], $c['action']); } catch (OutOfBoundsException $e) { $threw = true; }
    check($threw, "L:invalid {$c['state']}--{$c['action']} throws");
}
check($flow->start() === 'config_form' && $flow->isTerminal('order_complete'), "L:start/terminal");
check($flow->isTerminal('cancelled'), "L:terminal(cancelled)");
check($flow->actions('order_form') === ['submit','back','invalid','cancel'], "L:actions(order_form)");
check($flow->actions('catalog') === ['select','logout'], "L:actions(catalog)");

// ---- Suite FR: form-flow reachability — shortest path reaches each state ----
$FR = json_decode(file_get_contents(__DIR__ . '/flow-reach-fixtures.json'), true);
foreach ($FR as $state => $path) {
    check($flow->walk($path) === $state, "FR:reach $state via [" . implode(',', $path) . "]");
}
check(count($FR) === 7, "FR:all-states-reachable(7)");

// ---- Suite N: content scanner — find embedded tags in a document ----
require_once __DIR__ . '/scan.php';
$N = json_decode(file_get_contents(__DIR__ . '/scan-fixtures.json'), true);
foreach ($N as $c) {
    $tags = array_values(TagScanner::scan($c['document']));
    check($tags === $c['tags'],       "N:tags " . json_encode($c['document']));
    check(count($tags) === $c['count'],"N:count " . json_encode($c['document']));
}
// every scanned invocation is itself parseable back to a name (scanner ⊆ parser domain)
foreach ($N as $c) foreach ($c['tags'] as $inv) {
    check(parse_state($inv)['name'] !== null, "N:parseable $inv");
}

// ---- Suite RW: scan.php exactly mirrors the REAL Tag_Wrapper::identify_tag ----
require_once $ENTRY . '/lib/TagLoader/Tag/Tag_Wrapper.php';
$rwWrapper = Tag_Wrapper::create_document_tag('');
$rwMethod  = new ReflectionMethod('Tag_Wrapper', 'identify_tag');
$rwMethod->setAccessible(true);
foreach ($N as $c) {
    $real = array_values($rwMethod->invoke($rwWrapper, $c['document']));
    check($real === TagScanner::scan($c['document']), "RW:mirror " . json_encode($c['document']));
    check($real === $c['tags'], "RW:real-matches-fixture " . json_encode($c['document']));
}

// ---- Suite BSC: inbound pipeline — build -> embed -> scan -> parse ----
$BSC = json_decode(file_get_contents(__DIR__ . '/build-scan-fixtures.json'), true);
foreach ($BSC as $c) {
    $tag = TagBuilder::build($c['name'], $c['source_args']);
    check($tag === $c['tag'], "BSC:build {$c['name']}");
    $scanned = array_values(TagScanner::scan($c['doc']));
    check($scanned === $c['scanned'], "BSC:scan {$c['name']}");
    $parsed = array_map(function($inv){
        $p = Tag_Parser::get_tag_parser($inv);
        return ['name'=>$p->get_function_name(), 'args'=>array_values($p->get_tag_arguments()->getArguments() ?? [])];
    }, $scanned);
    check($parsed === $c['parsed'], "BSC:parse {$c['name']}");
}

// ---- Suite P: computational tags — a tag drives the eval core (goals #2+#4) ----
require __DIR__ . '/compute.php';
$comp = new TagComputer();
$P = json_decode(file_get_contents(__DIR__ . '/compute-fixtures.json'), true);
foreach ($P as $c) {
    check($comp->compute($c['tag']) === $c['expected'], "P:compute {$c['tag']}");
    check($comp->computeToString($c['tag']) === (string)$c['expected'], "P:string {$c['tag']}");
}
$threwName = false;
try { $comp->compute('<<<Title>>>'); } catch (OutOfBoundsException $e) { $threwName = true; }
check($threwName, "P:non-op-name-throws");
$threwArg = false;
try { $comp->compute('<<<add(x)(2)>>>'); } catch (InvalidArgumentException $e) { $threwArg = true; }
check($threwArg, "P:non-integer-arg-throws");

// ---- Suite CE: computational-tag error contract (tag-level exceptions) ----
$CE = json_decode(file_get_contents(__DIR__ . '/compute-errors-fixtures.json'), true);
foreach ($CE as $c) {
    $thrown = null;
    try { $comp->compute($c['tag']); } catch (\Throwable $e) { $thrown = get_class($e); }
    check($thrown === $c['error'], "CE:{$c['tag']} -> {$c['error']}");
}

// ---- Suite P2: systematic computational-tag sweep (binary ops x operands) ----
$P2 = json_decode(file_get_contents(__DIR__ . '/compute-sweep-fixtures.json'), true);
foreach ($P2 as $c) {
    check($comp->compute($c['tag']) === $c['expected'], "P2:{$c['tag']}");
}

// ---- Suite Q: unified document render — text + templates + computation ----
require __DIR__ . '/render_document.php';
$Q = json_decode(file_get_contents(__DIR__ . '/render-document-fixtures.json'), true);
$docRenderer = new DocumentRenderer($Q['templates']);
foreach ($Q['cases'] as $c) {
    check($docRenderer->render($c['document']) === $c['expected'], "Q:render " . json_encode($c['document']));
}
$threwUnres = false;
try { $docRenderer->render($Q['unresolved']); } catch (OutOfBoundsException $e) { $threwUnres = true; }
check($threwUnres, "Q:unresolved-tag-throws");

// ---- Suite HD: unified render with LIVE invocator-tag handlers (real TestTagA) ----
require_once $ENTRY . '/lib/Modules/taglib/Tag_Interface.php';
require_once $ENTRY . '/invocators/tags/test_tags/TestTagA.php';
$hdTemplates = ['Greeting' => 'Hi', 'Name' => 'Sam'];
$hdHandlers  = ['TestTagA' => fn($args, $inv) => (new TestTagA($args))->get_document()];
$hdRenderer  = new DocumentRenderer($hdTemplates, $hdHandlers);
$HD = json_decode(file_get_contents(__DIR__ . '/handler-fixtures.json'), true);
foreach ($HD as $c) {
    check($hdRenderer->render($c['document']) === $c['expected'], "HD:render " . json_encode($c['document']));
    check(TagScanner::scan($hdRenderer->render($c['document'])) === [], "HD:fully-resolved " . json_encode($c['document']));
}

// ---- Suite PRC: DocumentRenderer dispatch precedence = op > handler > template ----
$prcTemplates = ['add' => '[tmpl-add]', 'Foo' => '[tmpl-foo]', 'Plain' => '[plain-template]'];
$prcHandlers  = ['Foo' => fn($a,$i) => '[handler-foo]', 'Bar' => fn($a,$i) => '[handler-bar]'];
$prc = new DocumentRenderer($prcTemplates, $prcHandlers);
check($prc->render('<<<add(2)(3)>>>') === '5',              "PRC:op-beats-template");   // 'add' op wins over 'add' template
check($prc->render('<<<Foo>>>') === '[handler-foo]',        "PRC:handler-beats-template"); // 'Foo' handler wins over 'Foo' template
check($prc->render('<<<Bar>>>') === '[handler-bar]',        "PRC:handler-only");
check($prc->render('<<<Plain>>>') === '[plain-template]',   "PRC:template-only");

// ---- Suite O: recursive tag expansion (models execute_all_tags) ----
require __DIR__ . '/expand.php';
$O = json_decode(file_get_contents(__DIR__ . '/expand-fixtures.json'), true);
$expander = new TagExpander($O['templates']);
foreach ($O['cases'] as $c) {
    check($expander->expand($c['document']) === $c['expected'], "O:expand " . json_encode($c['document']));
}
foreach ($O['cycles'] as $cyc) {          // self, mutual (Ping<->Pong), and chain (A->B->C->A) cycles
    $threwCycle = false;
    try { $expander->expand($cyc); } catch (RuntimeException $e) { $threwCycle = true; }
    check($threwCycle, "O:cycle-depth-guard $cyc (self-heal, no hang)");
}
$threwUnknown = false;
try { $expander->expand($O['unknown']); } catch (OutOfBoundsException $e) { $threwUnknown = true; }
check($threwUnknown, "O:unknown-template-throws");

// ---- Suite U: real git-versioned store — history IS the database (goal #6) ----
require_once __DIR__ . '/gitstore.php';
$U = json_decode(file_get_contents(__DIR__ . '/git-store-fixtures.json'), true);
if (GitStore::gitAvailable()) {
    $gsRoot = sys_get_temp_dir() . '/cy-git-' . getmypid();
    $gs = new GitStore($gsRoot); $gs->init();
    $seq = 1;
    foreach ($U['versions'] as $v) $gs->put('tags', 'doc', $v['state'], $v['msg'], $seq++);
    check($gs->historyCount('tags','doc') === $U['expected_history'], "U:history-count");
    $n = count($U['versions']);
    check($gs->getHead('tags','doc') === $U['versions'][$n-1]['state'], "U:head-is-latest");
    foreach ($U['versions'] as $i => $v) {           // every past version reachable by ref
        $ref = 'HEAD~' . ($n - 1 - $i);
        check($gs->getAt($ref, 'tags', 'doc') === $v['state'], "U:version@$ref");
    }
    $gs->destroy();
} else {
    check(true, "U:git-unavailable-skip");
}

// ---- Suite M: git-backed document store — persistence round-trip (goal #6) ----
require __DIR__ . '/store.php';
$docStates = [];
foreach (['<<<Title>>>','<<<Body(content)>>>','<<<ItemList(catalog)(page)(admin)>>>',
          '<<<Login(user)(pass)>>>','<<<Show()>>>'] as $t) $docStates[] = parse_state($t);
foreach (['json','yaml','xml','html'] as $fmt) {
    $root = sys_get_temp_dir() . "/cy-store-$fmt-" . getmypid();
    $store = new DocumentStore($root, $fmt);
    foreach ($docStates as $i => $st) {
        $store->put('tags', "doc$i", $st);
        check($store->has('tags', "doc$i"), "M:has $fmt doc$i");
        check($store->get('tags', "doc$i") === $st, "M:roundtrip $fmt doc$i");
    }
    check($store->list('tags') === ['doc0','doc1','doc2','doc3','doc4'], "M:list $fmt");
    $threw = false;
    try { $store->get('tags','nope'); } catch (OutOfBoundsException $e) { $threw = true; }
    check($threw, "M:missing-throws $fmt");
    // collection persistence: a whole state-list stored + read back as one document
    $store->putCollection('pages', 'home', $docStates);
    check($store->getCollection('pages', 'home') === $docStates, "M:collection-roundtrip $fmt");
    $threwC = false;
    try { $store->getCollection('pages','ghost'); } catch (OutOfBoundsException $e) { $threwC = true; }
    check($threwC, "M:collection-missing-throws $fmt");
    // cleanup temp store (no repo pollution)
    array_map('unlink', glob("$root/tags/*"));
    array_map('unlink', glob("$root/pages/*"));
    @rmdir("$root/tags"); @rmdir("$root/pages"); @rmdir($root);
}

// ---- Suite BS: content-addressed blob store — hash stability + dedup (goal #6) ----
require __DIR__ . '/blobstore.php';
$BSf = json_decode(file_get_contents(__DIR__ . '/blob-fixtures.json'), true);
$bsRoot = sys_get_temp_dir() . '/cy-blob-' . getmypid();
$bs = new BlobStore($bsRoot);
foreach ($BSf as $c) {
    check($bs->hash($c['state']) === $c['hash'], "BS:hash {$c['state']['name']}");
    check($bs->put($c['state']) === $c['hash'], "BS:put-returns-hash {$c['state']['name']}");
    check($bs->get($c['hash']) === $c['state'], "BS:get-roundtrip {$c['state']['name']}");
}
$before = $bs->countObjects();
foreach ($BSf as $c) $bs->put($c['state']);              // re-put identical states
check($bs->countObjects() === $before, "BS:dedup (re-put adds no objects)");
check($before === count($BSf), "BS:object-count(" . count($BSf) . ")");
$threwB = false;
try { $bs->get(str_repeat('0', 40)); } catch (OutOfBoundsException $e) { $threwB = true; }
check($threwB, "BS:missing-object-throws");
exec('rm -rf ' . escapeshellarg($bsRoot));

// ---- Suite Y: etc-vs-www constant-syntax divergence (subprocess, real config) ----
$vf = json_decode(file_get_contents(__DIR__ . '/variant-fixtures.json'), true);
foreach (['etc','www'] as $v) {
    $o = []; $rc = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/probe_variant.php') . ' ' . $v . ' 2>/dev/null', $o, $rc);
    check($rc === 0 && json_decode(implode("\n", $o), true) === $vf[$v], "Y:variant-$v reproduces");
}
$diverge = 0;
foreach ($vf['etc'] as $i => $e) {
    if ($e['name'] !== $vf['www'][$i]['name'] || $e['argc'] !== $vf['www'][$i]['argc']) $diverge++;
}
check($diverge >= 3, "Y:underscore-divergence-present ($diverge inputs differ)");

// ---- Suite RT: real invocator tag (TestTagA) — output + full recursive expansion ----
require_once $ENTRY . '/lib/Modules/taglib/Tag_Interface.php';
require_once $ENTRY . '/invocators/tags/test_tags/TestTagA.php';
$RTd = json_decode(file_get_contents(__DIR__ . '/realtag-fixtures.json'), true);
foreach ($RTd as $c) {
    $doc = (new TestTagA(TagArguments::argumentFactory($c['call'])))->get_document();
    check($doc === $c['document'], "RT:document {$c['call']}");
    check(array_values(TagScanner::scan($doc)) === $c['children'], "RT:children {$c['call']}");
}
// Drive the REAL tag's recursion through the scanner until it reaches the base case.
$content = "<<<TestTagA(4)>>>"; $steps = 0;
while (($tags = TagScanner::scan($content)) && $steps < 50) {
    foreach ($tags as $inv) {
        $args = Tag_Parser::get_tag_parser($inv)->get_tag_arguments();
        $content = str_replace($inv, (new TestTagA($args))->get_document(), $content);
    }
    $steps++;
}
check(str_contains($content, 'The base case.') && TagScanner::scan($content) === [], "RT:full-expansion-terminates");
check($steps === 5, "RT:expansion-levels(5)");

// ---- Suite BR: real BugReport tag — catalog facts + zero-JS invariant (goal #5) ----
require_once $ENTRY . '/invocators/tags/dev/BugReport.php';
$BR = json_decode(file_get_contents(__DIR__ . '/bugreport-fixtures.json'), true);
$brDoc = (new BugReport(TagArguments::argumentFactory("BugReport")))->get_document();
preg_match_all('/BUG-\d+/', $brDoc, $brm);
check(array_values(array_unique($brm[0])) === $BR['bug_ids'], "BR:bug-ids");
foreach ($BR['severity_counts'] as $sev => $n) {
    check(substr_count($brDoc, $sev) === $n, "BR:severity-$sev($n)");
}
check(strlen($brDoc) === $BR['length'], "BR:length");
check((stripos($brDoc, '<script') !== false) === $BR['has_script'], "BR:has-script-flag");
check($BR['has_script'] === false, "BR:zero-javascript (goal #5)");

// ---- Suite K: self-description reconciles with reality (goal #3) ----
[$missing, $orphan] = $corpus->drift();
check($missing === [], "K:no-missing-datasets" . ($missing ? ' -> '.implode(',',$missing) : ''));
check($orphan  === [], "K:no-undescribed-orphans" . ($orphan ? ' -> '.implode(',',$orphan) : ''));
foreach ($corpus->datasets() as $d) {
    check(in_array($d['kind'], $corpus->kinds(), true), "K:kind-known {$d['id']}");
    check(is_array($corpus->load($d['id'])) && $corpus->load($d['id']) !== [], "K:nonempty {$d['id']}");
}
check(count($corpus->datasets()) === 38, "K:dataset-count(38)");

// ---- Suite HL: self-healing — regenerate a corrupted dataset (goal #3) ----
$healRoot = sys_get_temp_dir() . '/cy-heal-' . getmypid();
@mkdir($healRoot, 0777, true);
file_put_contents("$healRoot/scan-fixtures.json", "{ not valid json at all ");   // simulate corruption
$healed = $corpus->heal($healRoot);
check(in_array('scan', $healed, true), "HL:scan-detected-and-healed");
check(json_decode(file_get_contents("$healRoot/scan-fixtures.json"), true) === $corpus->load('scan'), "HL:scan-restored-correctly");
check($corpus->heal($healRoot) === [], "HL:idempotent-when-all-valid");
exec('rm -rf ' . escapeshellarg($healRoot));

// ---- Suite X: generators reproduce their committed fixtures (reproducibility) ----
$php = PHP_BINARY;
$genCount = 0;
foreach ($corpus->datasets() as $d) {
    if (empty($d['generator'])) continue;
    $genCount++;
    $genPath = __DIR__ . '/' . $d['generator'];
    check(is_file($genPath), "X:generator-exists {$d['generator']}");
    $out = []; $rc = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($genPath) . ' 2>/dev/null', $out, $rc);
    $regen = json_decode(implode("\n", $out), true);
    check($rc === 0 && $regen === $corpus->load($d['id']), "X:reproducible {$d['id']}");
}
check($genCount >= 11, "X:generator-coverage($genCount)");

// ---- report ----
$total = $pass + $fail;
echo "parser corpus: $pass/$total assertions passed\n";
if ($fail) { echo "FAILURES:\n"; foreach ($fails as $f) echo "  - $f\n"; exit(1); }
echo "OK\n"; exit(0);
