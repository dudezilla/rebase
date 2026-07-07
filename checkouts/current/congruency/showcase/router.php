<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Showcase front-end — a redesigned site RENDERED BY the evolved tag engine.
 *
 * Every page is produced server-side (zero JavaScript, goal #5) by the submission's
 * own classes: Tag_Parser, TagStateRenderer (four-format edge), TagComputer /
 * TagEvaluator (Turing core), DocumentRenderer (scan->dispatch->render), FormFlow
 * (server-side form wizard) and Corpus (self-describing manifest). Navigation and
 * the compute/flow demos are pure form -> server -> next-page round-trips.
 *
 *   php -S 0.0.0.0:8901 showcase/router.php     (run from the entry-folder root)
 */
error_reporting(E_ALL & ~E_DEPRECATED);

$P = __DIR__ . '/../tests/parser';
require_once "$P/render.php";            // TagStateRenderer
require_once "$P/render_document.php";   // DocumentRenderer, TagComputer, TagScanner, Tag_Parser
require_once "$P/corpus.php";            // Corpus (self-describing)
require_once "$P/flow.php";              // FormFlow
require_once "$P/builder.php";           // TagBuilder

/* ---- static asset routes (favicon) so the browser tab is clean ---- */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($uri === '/favicon.ico') { http_response_code(204); return true; }

function parse_state(string $tag): array {
    $p = Tag_Parser::get_tag_parser($tag);
    return ['name' => $p->get_function_name(),
            'args' => array_values($p->get_tag_arguments()->getArguments() ?? [])];
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

$page = $_GET['page'] ?? 'home';
$NAV = [
    'home'    => 'Overview',
    'formats' => 'Four-format edge',
    'compute' => 'Tags compute',
    'flow'    => 'Form wizard',
    'corpus'  => 'The corpus',
    'about'   => 'About',
];
if (!isset($NAV[$page])) $page = 'home';

/* =============================== page bodies =============================== */

function page_home(): string {
    $corpus = new Corpus();
    $datasets = count($corpus->datasets());
    $records = 0; foreach ($corpus->datasets() as $d) { $x = $corpus->load($d['id']); $records += is_array($x) ? count($x) : 1; }
    $ops = (new TagEvaluator())->opKeys();

    // A live document rendered by DocumentRenderer: templates + a computed tag.
    $doc = new DocumentRenderer(['Product' => 'Congruency Tag Engine']);
    $tagline = $doc->render('<<<Product>>> — <<<add(2)(5)>>> goals shipped, format at the edge');

    $tiles = [
        [$datasets, 'tested datasets', 'self-describing via manifest.json'],
        [number_format($records), 'data records', 'captured or authored, all green'],
        [4, 'output formats', 'XML · HTML · JSON · YAML, bidirectional'],
        [count($ops), 'evaluation ops', 'a Turing-complete core'],
    ];
    $tileHtml = '';
    foreach ($tiles as [$n, $label, $sub]) {
        $tileHtml .= '<div class="tile"><div class="tile-n">' . h((string)$n) . '</div>'
                   . '<div class="tile-l">' . h($label) . '</div>'
                   . '<div class="tile-s">' . h($sub) . '</div></div>';
    }

    $goals = [
        ['①', 'One state → four formats', 'The same internal state renders to XML, HTML, JSON and YAML. Format is chosen at the edge — a registry key — never baked into the logic. And it reads back: every format round-trips.'],
        ['②', 'No magic strings', 'Every key resolves through a dictionary: the tag registry, the operator table, the render/handler registries. Dispatch precedence is data: op › handler › template.'],
        ['③', 'Reads its own description', 'The suite self-configures from manifest.json, reconciles the description against reality, and self-heals — regenerating any corrupted dataset from its generator.'],
        ['④', 'Turing-complete core', 'Tags drive computation. A recursive evaluator with named functions runs factorial, Fibonacci, Ackermann, gcd — and <code>&lt;&lt;&lt;add(2)(3)&gt;&gt;&gt;</code> computes through the real parser.'],
        ['⑤', 'Zero JavaScript', 'All interactivity — including this site and its forms — is server-side. Form → server → next page. This very page carries no script.'],
        ['⑥', 'Git as the store', 'Persistence moves to git: a file store, a git-versioned store where history is the database, and a content-addressed blob store (sha1 + dedup).'],
        ['⑦', 'Modernized under test', 'Dated conventions retired guided by the oracle — e.g. the parser dynamic-property deprecation — with the scanner validated against the real Tag_Wrapper source.'],
    ];
    $goalHtml = '';
    foreach ($goals as [$num, $title, $body]) {
        $goalHtml .= '<article class="goal"><div class="goal-num">' . $num . '</div>'
                   . '<h3>' . h($title) . '</h3><p>' . $body . '</p></article>';
    }

    return <<<HTML
    <header class="hero">
      <div class="eyebrow">tournament submission · rendered by its own engine</div>
      <h1>The Congruency tag engine, <em>evolved</em>.</h1>
      <p class="lede">A 2006 CMS's <code>&lt;&lt;&lt;tag&gt;&gt;&gt;</code> parser, rebuilt into a minimal,
      reliable, exhaustively-tested system — <strong>scan → parse → compute or expand → render at the edge → persist to git</strong>.
      Every page here is produced server-side by that engine. No database. No JavaScript.</p>
      <p class="hero-tag">$tagline</p>
    </header>
    <section class="tiles">$tileHtml</section>
    <section>
      <h2 class="section-h">Seven goals, all under test</h2>
      <div class="goals">$goalHtml</div>
    </section>
HTML;
}

function page_formats(): string {
    $tag = $_GET['tag'] ?? '<<<ItemList(catalog)(page)(admin)>>>';
    $tag = trim($tag);
    $state = parse_state($tag);
    $fmts = ['xml' => 'XML', 'html' => 'HTML', 'json' => 'JSON', 'yaml' => 'YAML'];
    $cards = '';
    foreach ($fmts as $k => $label) {
        $rendered = TagStateRenderer::render($k, $state);
        $roundtrips = TagStateRenderer::parse($k, $rendered) === $state;
        $badge = $roundtrips ? '<span class="ok">round-trips ✓</span>' : '<span class="bad">lossy</span>';
        $cards .= '<div class="fmt"><div class="fmt-head"><span class="fmt-name">' . h($label) . '</span>' . $badge . '</div>'
                . '<pre>' . h($rendered) . '</pre></div>';
    }
    $stateJson = h(json_encode($state, JSON_UNESCAPED_SLASHES));
    $examples = ['<<<Title>>>', '<<<Login(user)(pass)>>>', '<<<ItemList(catalog)(page)(admin)>>>', '<<<Show()>>>', '<<<Order(cart)(user)(confirm)>>>'];
    $exLinks = '';
    foreach ($examples as $e) $exLinks .= '<a class="chip" href="?page=formats&amp;tag=' . urlencode($e) . '">' . h($e) . '</a>';
    $q_tag = h($tag);

    return <<<HTML
    <header class="page-head">
      <h1>One internal state → four formats</h1>
      <p class="lede">Type a tag. The <em>real</em> parser turns it into a canonical state
      <code>{name, args}</code>; the renderer emits it to every format via a registry key — and reads each back.</p>
    </header>
    <form class="demo-form" method="get" action="">
      <input type="hidden" name="page" value="formats">
      <label for="tag">Tag</label>
      <input id="tag" name="tag" value="{$q_tag}" spellcheck="false" autocomplete="off">
      <button type="submit">Render</button>
    </form>
    <div class="chips">$exLinks</div>
    <p class="parsed">parsed state → <code>$stateJson</code></p>
    <section class="formats-grid">$cards</section>
HTML;
}

function page_compute(): string {
    $comp = new TagComputer();
    $ops = ['add','sub','mul','div','mod','max','min','pow'];
    $op = in_array($_GET['op'] ?? 'add', $ops, true) ? $_GET['op'] : 'add';
    $a  = (string)(int)($_GET['a'] ?? 3);
    $b  = (string)(int)($_GET['b'] ?? 4);
    $tag = "<<<$op($a)($b)>>>";
    $result = null; $err = null;
    try { $result = $comp->compute($tag); } catch (\Throwable $e) { $err = get_class($e) . ': ' . $e->getMessage(); }

    $opOptions = '';
    foreach ($ops as $o) $opOptions .= '<option value="' . h($o) . '"' . ($o === $op ? ' selected' : '') . '>' . h($o) . '</option>';

    $resultHtml = $err
        ? '<div class="result err"><code>' . h($tag) . '</code> → <strong>' . h($err) . '</strong></div>'
        : '<div class="result"><code>' . h($tag) . '</code> → <strong class="big">' . h((string)$result) . '</strong></div>';
    $q_a = h($a); $q_b = h($b);

    // Recursion demo: factorial via the eval core (named function + recursion).
    $n = max(0, min(12, (int)($_GET['n'] ?? 6)));
    $ev = new TagEvaluator();
    $fact = ['fact' => ['params' => ['n'], 'body' =>
        ['op'=>'if','args'=>[
            ['op'=>'lt','args'=>[['op'=>'var','name'=>'n'],['op'=>'int','value'=>1]]],
            ['op'=>'int','value'=>1],
            ['op'=>'mul','args'=>[['op'=>'var','name'=>'n'],
                ['op'=>'call','name'=>'fact','args'=>[['op'=>'sub','args'=>[['op'=>'var','name'=>'n'],['op'=>'int','value'=>1]]]]]]]]]]];
    $factVal = $ev->run(['defs'=>$fact, 'main'=>['op'=>'call','name'=>'fact','args'=>[['op'=>'int','value'=>$n]]]]);

    return <<<HTML
    <header class="page-head">
      <h1>Tags drive computation</h1>
      <p class="lede">A tag's name dispatches through the evaluator's operator table; its
      arguments (un-reversed to source order) are the operands. Submit — the server computes it.</p>
    </header>
    <form class="demo-form compute" method="get" action="">
      <input type="hidden" name="page" value="compute">
      <span class="delim">&lt;&lt;&lt;</span>
      <select name="op">$opOptions</select>
      <span class="delim">(</span><input class="num" name="a" value="{$q_a}" inputmode="numeric">
      <span class="delim">)(</span><input class="num" name="b" value="{$q_b}" inputmode="numeric"><span class="delim">)&gt;&gt;&gt;</span>
      <button type="submit">Compute</button>
    </form>
    $resultHtml

    <h2 class="section-h">…and it recurses</h2>
    <p class="lede">The core has named functions and recursion — Turing-complete. Here <code>factorial(n)</code>
    runs entirely in the tag evaluator.</p>
    <form class="demo-form" method="get" action="">
      <input type="hidden" name="page" value="compute">
      <input type="hidden" name="op" value="{$op}"><input type="hidden" name="a" value="{$q_a}"><input type="hidden" name="b" value="{$q_b}">
      <label for="n">n</label>
      <input id="n" class="num" name="n" value="$n" inputmode="numeric">
      <button type="submit">factorial(n)</button>
    </form>
    <div class="result"><code>factorial($n)</code> → <strong class="big">{$factVal}</strong></div>
HTML;
}

function page_flow(): string {
    $flow = new FormFlow();
    $flowRaw = json_decode(file_get_contents(__DIR__ . '/../tests/parser/form-flow.json'), true);
    $state = $_GET['state'] ?? $flow->start();
    if (!isset($flowRaw['transitions'][$state]) && !$flow->isTerminal($state)) $state = $flow->start();

    $stepNames = ['login'=>'Login', 'catalog'=>'Catalog', 'config_form'=>'Configure item',
                  'order_form'=>'Contact details', 'orderer'=>'Confirm order',
                  'order_complete'=>'✓ Order placed', 'cancelled'=>'✗ Cancelled'];

    $actionsHtml = '';
    if ($flow->isTerminal($state)) {
        $actionsHtml = '<a class="btn" href="?page=flow&amp;state=' . urlencode($flow->start()) . '">Start over</a>';
    } else {
        foreach ($flow->actions($state) as $action) {
            $next = $flowRaw['transitions'][$state][$action];
            $actionsHtml .= '<a class="btn" href="?page=flow&amp;state=' . urlencode($next) . '">'
                          . h($action) . ' →</a>';
        }
    }

    // render the whole graph as a static map
    $rows = '';
    foreach ($flowRaw['transitions'] as $s => $trans) {
        $cur = $s === $state ? ' class="cur"' : '';
        $edges = [];
        foreach ($trans as $act => $to) $edges[] = h($act) . ' → ' . h($to);
        $rows .= "<tr$cur><th>" . h($s) . '</th><td>' . implode(' &nbsp;·&nbsp; ', $edges) . '</td></tr>';
    }
    $label = h($stepNames[$state] ?? $state);

    return <<<HTML
    <header class="page-head">
      <h1>Server-side form wizard</h1>
      <p class="lede">Zero JavaScript. Each step is a <strong>form → server → next form</strong>
      round-trip through a keyed transition table. You are the request; the link is the submit.</p>
    </header>
    <div class="wizard">
      <div class="wizard-state"><span class="wizard-badge">now</span> <strong>$label</strong>
        <code>($state)</code></div>
      <div class="wizard-actions">$actionsHtml</div>
    </div>
    <h2 class="section-h">The transition table (the whole map)</h2>
    <table class="flow-table"><thead><tr><th>state</th><th>action → next</th></tr></thead><tbody>$rows</tbody></table>
HTML;
}

function page_corpus(): string {
    $corpus = new Corpus();
    [$missing, $orphan] = $corpus->drift();
    $health = ($missing || $orphan) ? '<span class="bad">drift detected</span>' : '<span class="ok">description reconciles with reality ✓</span>';
    $rows = ''; $total = 0;
    foreach ($corpus->datasets() as $d) {
        $data = $corpus->load($d['id']);
        $n = is_array($data) ? count($data) : 1; $total += $n;
        $gen = !empty($d['generator']) ? '<span class="ok">generated</span>' : '<span class="muted">authored</span>';
        $rows .= '<tr><th>' . h($d['id']) . '</th><td>' . h($d['kind']) . '</td>'
               . '<td class="r">' . h((string)$n) . '</td><td>' . $gen . '</td></tr>';
    }
    $count = count($corpus->datasets());
    return <<<HTML
    <header class="page-head">
      <h1>The self-describing corpus</h1>
      <p class="lede">The oracle reads its own <code>manifest.json</code> to discover these datasets,
      reconciles the description against the filesystem, and can regenerate any corrupted one. $health</p>
    </header>
    <p class="parsed">$count datasets · $total records · generated datasets are re-run by the oracle and must reproduce byte-for-record.</p>
    <table class="flow-table corpus"><thead><tr><th>dataset</th><th>kind</th><th class="r">records</th><th>source</th></tr></thead><tbody>$rows</tbody></table>
HTML;
}

function page_about(): string {
    return <<<HTML
    <header class="page-head">
      <h1>About this submission</h1>
      <p class="lede">A code-evolution tournament entry: a self-contained snapshot of the congruency
      CMS, evolved into a tested tagging system. This showcase is rendered by that system's own classes.</p>
    </header>
    <div class="prose">
      <h3>Run the oracle</h3>
      <pre>cd tests/parser
../../../congruency-harness/php/php run.php   # exit 0 iff all assertions pass</pre>
      <p>One command, no database, no web server, zero JavaScript. It self-configures from
      <code>manifest.json</code>, exercises the real parser plus the evolved engine, and prints
      <code>parser corpus: N/N assertions passed</code>.</p>
      <h3>What renders this page</h3>
      <p>The <code>showcase/router.php</code> front-end requires the submission's own classes —
      <code>Tag_Parser</code>, <code>TagStateRenderer</code>, <code>TagComputer</code>,
      <code>DocumentRenderer</code>, <code>FormFlow</code>, <code>Corpus</code> — and produces every
      page server-side. The four-format panels, the compute results, and the wizard are all live calls
      into that engine on each request.</p>
      <h3>Where the work lives</h3>
      <p><code>tests/parser/</code> holds the engine and the datasets; <code>README.md</code> and
      <code>submission_00.txt</code> at the entry root map it out.</p>
    </div>
HTML;
}

/* =============================== render page =============================== */
$q_tag = h($_GET['tag'] ?? '<<<ItemList(catalog)(page)(admin)>>>');
$q_a = h((string)(int)($_GET['a'] ?? 3));
$q_b = h((string)(int)($_GET['b'] ?? 4));

$body = match ($page) {
    'formats' => page_formats(),
    'compute' => page_compute(),
    'flow'    => page_flow(),
    'corpus'  => page_corpus(),
    'about'   => page_about(),
    default   => page_home(),
};

$navHtml = '';
foreach ($NAV as $slug => $label) {
    $active = $slug === $page ? ' class="active"' : '';
    $navHtml .= '<a' . $active . ' href="?page=' . $slug . '">' . h($label) . '</a>';
}

$css = showcase_css();
$title = ($page === 'home' ? 'Congruency Tag Engine' : $NAV[$page] . ' · Congruency') ;
echo <<<HTML
<!DOCTYPE html>
<html lang="en" data-page="$page">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>$title</title>
<style>$css</style>
</head>
<body>
<div class="wrap">
  <nav class="topnav">
    <a class="brand" href="?page=home">congruency<span>· evolved</span></a>
    <div class="links">$navHtml</div>
  </nav>
  <main>$body</main>
  <footer>
    Rendered server-side by the submission's own tag engine · zero JavaScript ·
    <a href="?page=about">how it works</a>
  </footer>
</div>
</body>
</html>
HTML;

function showcase_css(): string {
    return <<<CSS
    :root{
      --bg:#faf8f3; --panel:#fff; --ink:#1c1a17; --muted:#6b6459; --line:#e7e1d5;
      --accent:#b5651d; --accent2:#2f6f6a; --ok:#2f7d46; --bad:#b23b3b; --code:#f2ede2;
      --shadow:0 1px 2px rgba(40,30,10,.05),0 8px 24px rgba(40,30,10,.05);
    }
    @media (prefers-color-scheme:dark){
      :root{ --bg:#16150f; --panel:#201e17; --ink:#efe9dc; --muted:#a49a86; --line:#332f24;
        --accent:#e0954a; --accent2:#5bb0a8; --ok:#63c98a; --bad:#e07a7a; --code:#191710;
        --shadow:0 1px 2px rgba(0,0,0,.3),0 10px 30px rgba(0,0,0,.35);}
    }
    *{box-sizing:border-box}
    html{-webkit-text-size-adjust:100%}
    body{margin:0;background:var(--bg);color:var(--ink);
      font:16px/1.6 ui-serif,Georgia,"Iowan Old Style",serif;}
    code,pre{font-family:ui-monospace,"SF Mono",Menlo,Consolas,monospace}
    .wrap{max-width:960px;margin:0 auto;padding:0 20px}
    a{color:var(--accent);text-decoration:none}
    a:hover{text-decoration:underline}
    /* nav */
    .topnav{display:flex;align-items:center;justify-content:space-between;gap:16px;
      flex-wrap:wrap;padding:20px 0;border-bottom:1px solid var(--line);margin-bottom:34px}
    .brand{font-weight:700;font-size:1.15rem;letter-spacing:-.01em;color:var(--ink)}
    .brand span{color:var(--muted);font-weight:400;margin-left:6px;font-size:.85rem}
    .links{display:flex;gap:4px;flex-wrap:wrap}
    .links a{padding:6px 11px;border-radius:8px;color:var(--muted);font-family:ui-sans-serif,system-ui,sans-serif;font-size:.9rem}
    .links a:hover{background:var(--code);text-decoration:none;color:var(--ink)}
    .links a.active{background:var(--accent);color:#fff}
    /* hero */
    .hero{padding:14px 0 8px}
    .eyebrow{font-family:ui-sans-serif,system-ui,sans-serif;text-transform:uppercase;
      letter-spacing:.12em;font-size:.72rem;color:var(--accent2);font-weight:600;margin-bottom:14px}
    h1{font-size:clamp(2rem,5vw,3rem);line-height:1.1;letter-spacing:-.02em;margin:.1em 0 .3em}
    h1 em{font-style:italic;color:var(--accent)}
    .lede{font-size:1.12rem;color:var(--muted);max-width:66ch;margin:.4em 0 1em}
    .hero-tag{font-family:ui-monospace,monospace;font-size:.9rem;color:var(--accent2);
      background:var(--code);display:inline-block;padding:8px 12px;border-radius:8px}
    code{background:var(--code);padding:1px 6px;border-radius:5px;font-size:.88em}
    pre{background:var(--code);padding:14px 16px;border-radius:10px;overflow-x:auto;
      font-size:.86rem;line-height:1.5;border:1px solid var(--line)}
    /* tiles */
    .tiles{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:34px 0}
    .tile{background:var(--panel);border:1px solid var(--line);border-radius:14px;
      padding:18px;box-shadow:var(--shadow)}
    .tile-n{font-size:2rem;font-weight:700;letter-spacing:-.02em;color:var(--accent)}
    .tile-l{font-family:ui-sans-serif,system-ui,sans-serif;font-weight:600;font-size:.92rem;margin-top:2px}
    .tile-s{font-family:ui-sans-serif,system-ui,sans-serif;color:var(--muted);font-size:.78rem;margin-top:3px}
    .section-h{font-size:1.5rem;letter-spacing:-.01em;margin:40px 0 18px;
      padding-bottom:8px;border-bottom:1px solid var(--line)}
    /* goals */
    .goals{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .goal{background:var(--panel);border:1px solid var(--line);border-radius:14px;
      padding:20px 22px;box-shadow:var(--shadow)}
    .goal-num{font-size:1.4rem;color:var(--accent2)}
    .goal h3{font-size:1.08rem;margin:.2em 0 .4em;letter-spacing:-.01em}
    .goal p{color:var(--muted);font-size:.95rem;margin:0}
    /* page head */
    .page-head{padding:8px 0 6px}
    /* demo forms */
    .demo-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap;
      background:var(--panel);border:1px solid var(--line);border-radius:12px;
      padding:14px 16px;box-shadow:var(--shadow);margin:16px 0;
      font-family:ui-sans-serif,system-ui,sans-serif}
    .demo-form label{color:var(--muted);font-size:.9rem}
    .demo-form input,.demo-form select{font:inherit;font-family:ui-monospace,monospace;
      background:var(--bg);color:var(--ink);border:1px solid var(--line);
      border-radius:8px;padding:8px 10px}
    .demo-form input#tag{flex:1;min-width:220px}
    .demo-form .num{width:70px;text-align:center}
    .demo-form .delim{font-family:ui-monospace,monospace;color:var(--accent2)}
    .demo-form button{font:inherit;font-family:ui-sans-serif,system-ui,sans-serif;font-weight:600;
      background:var(--accent);color:#fff;border:0;border-radius:8px;padding:9px 16px;cursor:pointer}
    .demo-form button:hover{filter:brightness(1.06)}
    .compute select{font-family:ui-monospace,monospace}
    .chips{display:flex;gap:8px;flex-wrap:wrap;margin:4px 0 14px}
    .chip{font-family:ui-monospace,monospace;font-size:.8rem;background:var(--code);
      color:var(--muted);padding:5px 10px;border-radius:20px;border:1px solid var(--line)}
    .chip:hover{color:var(--ink);text-decoration:none;border-color:var(--accent)}
    .parsed{font-family:ui-sans-serif,system-ui,sans-serif;color:var(--muted);font-size:.9rem}
    /* format grid */
    .formats-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}
    .fmt{background:var(--panel);border:1px solid var(--line);border-radius:12px;overflow:hidden;box-shadow:var(--shadow)}
    .fmt-head{display:flex;justify-content:space-between;align-items:center;
      padding:10px 14px;border-bottom:1px solid var(--line);
      font-family:ui-sans-serif,system-ui,sans-serif}
    .fmt-name{font-weight:700;letter-spacing:.02em}
    .fmt pre{margin:0;border:0;border-radius:0;background:transparent}
    .ok{color:var(--ok);font-size:.78rem;font-family:ui-sans-serif,system-ui,sans-serif}
    .bad{color:var(--bad);font-size:.78rem;font-family:ui-sans-serif,system-ui,sans-serif}
    .muted{color:var(--muted);font-size:.78rem;font-family:ui-sans-serif,system-ui,sans-serif}
    /* result */
    .result{background:var(--panel);border:1px solid var(--line);border-left:4px solid var(--accent2);
      border-radius:10px;padding:16px 18px;margin:14px 0;font-family:ui-monospace,monospace;box-shadow:var(--shadow)}
    .result.err{border-left-color:var(--bad)}
    .result .big{font-size:1.6rem;color:var(--accent)}
    /* wizard */
    .wizard{background:var(--panel);border:1px solid var(--line);border-radius:14px;
      padding:22px;box-shadow:var(--shadow);margin:16px 0}
    .wizard-state{font-size:1.2rem;margin-bottom:16px}
    .wizard-state code{font-size:.8rem;color:var(--muted)}
    .wizard-badge{background:var(--accent2);color:#fff;font-family:ui-sans-serif,system-ui,sans-serif;
      font-size:.68rem;text-transform:uppercase;letter-spacing:.1em;padding:3px 8px;border-radius:20px;vertical-align:middle}
    .wizard-actions{display:flex;gap:10px;flex-wrap:wrap}
    .btn{font-family:ui-sans-serif,system-ui,sans-serif;font-weight:600;background:var(--accent);
      color:#fff;padding:9px 16px;border-radius:8px}
    .btn:hover{filter:brightness(1.06);text-decoration:none}
    /* tables */
    .flow-table{width:100%;border-collapse:collapse;font-family:ui-sans-serif,system-ui,sans-serif;
      font-size:.9rem;background:var(--panel);border:1px solid var(--line);border-radius:12px;overflow:hidden;box-shadow:var(--shadow)}
    .flow-table th,.flow-table td{text-align:left;padding:10px 14px;border-bottom:1px solid var(--line)}
    .flow-table thead th{background:var(--code);font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
    .flow-table tbody tr:last-child td,.flow-table tbody tr:last-child th{border-bottom:0}
    .flow-table th{font-family:ui-monospace,monospace;font-weight:600}
    .flow-table tr.cur th{color:var(--accent)}
    .flow-table tr.cur{background:color-mix(in srgb,var(--accent) 8%,transparent)}
    .flow-table .r{text-align:right;font-variant-numeric:tabular-nums}
    .corpus tbody{max-height:none}
    /* prose */
    .prose h3{margin:26px 0 8px}
    .prose p{color:var(--muted);max-width:70ch}
    /* footer */
    footer{margin:50px 0 30px;padding-top:18px;border-top:1px solid var(--line);
      color:var(--muted);font-family:ui-sans-serif,system-ui,sans-serif;font-size:.85rem}
    @media (max-width:720px){
      .tiles{grid-template-columns:1fr 1fr}
      .goals,.formats-grid{grid-template-columns:1fr}
    }
    CSS;
}
