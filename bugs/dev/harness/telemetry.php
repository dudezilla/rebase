<?php
/*
 * telemetry.php — comprehensive client-side telemetry + a telemetry database.
 *
 * The injected JS does ONLY telemetry (it observes; it never alters app
 * behaviour): pageview, full-DOM snapshots (on load and pre-submit), console
 * capture (log/info/warn/error, pass-through), JS errors, form submits, and
 * first field focus. Everything lands in a SEPARATE SQLite db (telemetry.sqlite).
 *
 * Endpoints (handled before the CMS boots):
 *   ?telemetry=1            POST beacon ingest (JSON body)
 *   ?telemetry=view         event viewer (HTML)
 *   ?telemetry=payload&id=N raw stored payload (a DOM snapshot / console line)
 */
define('TELEMETRY_DB', __DIR__ . '/telemetry.sqlite');

function telemetry_db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . TELEMETRY_DB);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $pdo->exec("CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ts INTEGER, session TEXT, page TEXT, event TEXT, detail TEXT,
            payload TEXT, bytes INTEGER, ua TEXT, ip TEXT)");
        $pdo->exec("ALTER TABLE events ADD COLUMN payload TEXT");   // no-op if it already exists
        $pdo->exec("ALTER TABLE events ADD COLUMN bytes INTEGER");
        // The attack stack: a prediction is recorded BEFORE the attack, the result after.
        $pdo->exec("CREATE TABLE IF NOT EXISTS attacks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            iteration INTEGER, name TEXT, page TEXT, vector TEXT,
            prediction TEXT, predicted_ts INTEGER,
            result TEXT, evidence TEXT, verdict TEXT, result_ts INTEGER)");
    }
    return $pdo;
}

function telemetry_session() { return $_COOKIE['PHPSESSID'] ?? 'anon'; }

function telemetry_handle() {
    if (!isset($_GET['telemetry'])) return;
    $mode = $_GET['telemetry'];

    if ($mode === '1') {                                   // ingest a beacon
        $d = json_decode(file_get_contents('php://input'), true);
        if (is_array($d)) {
            $payload = (string)($d['payload'] ?? '');
            telemetry_db()->prepare(
                "INSERT INTO events (ts,session,page,event,detail,payload,bytes,ua,ip)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([
                (int)($d['ts'] ?? 0), telemetry_session(),
                substr((string)($d['page'] ?? ''), 0, 64),
                substr((string)($d['event'] ?? ''), 0, 32),
                substr((string)($d['detail'] ?? ''), 0, 256),
                $payload, strlen($payload),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 160),
                $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        }
        http_response_code(204);
        exit;
    }

    if ($mode === 'payload') {                             // raw stored payload
        header('Content-Type: text/plain; charset=utf-8');
        $st = telemetry_db()->prepare("SELECT payload FROM events WHERE id=?");
        $st->execute([(int)($_GET['id'] ?? 0)]);
        echo (string)$st->fetchColumn();
        exit;
    }

    if ($mode === 'predict') {                            // record a prediction, return its id
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        telemetry_db()->prepare(
            "INSERT INTO attacks (iteration,name,page,vector,prediction,predicted_ts)
             VALUES (?,?,?,?,?,?)"
        )->execute([
            (int)($d['iteration'] ?? 0), (string)($d['name'] ?? ''), (string)($d['page'] ?? ''),
            (string)($d['vector'] ?? ''), (string)($d['prediction'] ?? ''), time(),
        ]);
        header('Content-Type: application/json');
        echo json_encode(['id' => (int)telemetry_db()->lastInsertId()]);
        exit;
    }

    if ($mode === 'result') {                             // attach the result to a prediction
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        telemetry_db()->prepare(
            "UPDATE attacks SET result=?, evidence=?, verdict=?, result_ts=? WHERE id=?"
        )->execute([
            (string)($d['result'] ?? ''), (string)($d['evidence'] ?? ''),
            (string)($d['verdict'] ?? ''), time(), (int)($d['id'] ?? 0),
        ]);
        http_response_code(204);
        exit;
    }

    if ($mode === 'attacks') { telemetry_attacks_view(); exit; }
    if ($mode === 'view') { telemetry_view(); exit; }
}

function telemetry_attacks_view() {
    header('Content-Type: text/html; charset=utf-8');
    $rows = telemetry_db()->query("SELECT * FROM attacks ORDER BY id DESC LIMIT 200");
    echo "<!doctype html><meta charset=utf-8><title>Attack stack</title><style>"
       . "body{font:13px system-ui,sans-serif;margin:2rem;background:#0f1115;color:#d8dee9}"
       . "h1{font-weight:500}table{border-collapse:collapse;width:100%}"
       . "th,td{padding:.4rem .6rem;border-bottom:1px solid #2a2f3a;text-align:left;vertical-align:top}"
       . "th{color:#88c0d0}.CONFIRMED{color:#a3be8c;font-weight:600}.REFUTED{color:#bf616a;font-weight:600}"
       . ".SAFE{color:#8fbcbb;font-weight:600}code{color:#ebcb8b;white-space:pre-wrap;word-break:break-all}</style>";
    echo "<h1>Attack stack &mdash; prediction vs result</h1><table>"
       . "<tr><th>#</th><th>iter</th><th>attack</th><th>page</th><th>prediction</th><th>result</th><th>evidence</th><th>verdict</th></tr>";
    foreach ($rows as $r) {
        echo "<tr><td>{$r['id']}</td><td>{$r['iteration']}</td><td>" . htmlspecialchars((string)$r['name'])
           . "</td><td>" . htmlspecialchars((string)$r['page'])
           . "</td><td>" . htmlspecialchars((string)$r['prediction'])
           . "</td><td>" . htmlspecialchars((string)$r['result'])
           . "</td><td><code>" . htmlspecialchars(substr((string)$r['evidence'], 0, 200))
           . "</code></td><td class='" . htmlspecialchars((string)$r['verdict']) . "'>"
           . htmlspecialchars((string)$r['verdict']) . "</td></tr>";
    }
    echo "</table>";
}

function telemetry_view() {
    header('Content-Type: text/html; charset=utf-8');
    $rows = telemetry_db()->query(
        "SELECT id,ts,session,page,event,detail,bytes FROM events ORDER BY id DESC LIMIT 300");
    echo "<!doctype html><meta charset=utf-8><title>Congruency telemetry</title><style>"
       . "body{font:14px system-ui,sans-serif;margin:2rem;background:#0f1115;color:#d8dee9}"
       . "h1{font-weight:500}table{border-collapse:collapse;width:100%}"
       . "th,td{padding:.35rem .7rem;border-bottom:1px solid #2a2f3a;text-align:left;vertical-align:top}"
       . "th{color:#88c0d0}tr:hover{background:#161a22}"
       . ".pageview{color:#88c0d0}.form_submit{color:#ebcb8b}.field_focus{color:#8fbcbb}"
       . ".console{color:#a3be8c}.error{color:#bf616a}.dom{color:#b48ead}"
       . "a{color:#81a1c1}code{color:#ebcb8b}</style>";
    echo "<h1>Congruency telemetry</h1><p>Most recent 300 events &middot; "
       . "<a href='?telemetry=view'>refresh</a></p>"
       . "<table><tr><th>time</th><th>session</th><th>page</th><th>event</th><th>detail</th><th>payload</th></tr>";
    foreach ($rows as $r) {
        $t = $r['ts'] ? gmdate('H:i:s', (int)($r['ts'] / 1000)) : '';
        $pl = ($r['bytes'] > 0)
            ? "<a href='?telemetry=payload&id={$r['id']}'>" . number_format($r['bytes']) . " B</a>"
            : "";
        echo "<tr><td>$t</td><td><code>" . htmlspecialchars(substr((string)$r['session'], 0, 8))
           . "</code></td><td>" . htmlspecialchars((string)$r['page'])
           . "</td><td class='" . htmlspecialchars((string)$r['event']) . "'>" . htmlspecialchars((string)$r['event'])
           . "</td><td>" . htmlspecialchars((string)$r['detail'])
           . "</td><td>$pl</td></tr>";
    }
    echo "</table>";
}

/* The telemetry-only client snippet, injected before </body> of every page. */
function telemetry_script() {
    return "\n<script>(function(){"
        . "var q=new URLSearchParams(location.search),page=q.get('page')||'catalog';"
        . "function send(ev,detail,payload){try{navigator.sendBeacon('?telemetry=1',"
        . "JSON.stringify({event:ev,page:page,detail:detail||'',payload:payload||'',ts:Date.now()}));}catch(e){}}"
        // console capture (pass-through: telemetry only, behaviour unchanged)
        . "['log','info','warn','error'].forEach(function(m){var o=console[m];console[m]=function(){"
        . "try{send('console',m,Array.prototype.map.call(arguments,String).join(' '));}catch(e){}"
        . "return o.apply(console,arguments);};});"
        // uncaught errors + rejections
        . "window.addEventListener('error',function(e){send('error','onerror',(e.message||'')+' @ '+(e.filename||'')+':'+(e.lineno||''));});"
        . "window.addEventListener('unhandledrejection',function(e){send('error','promise',String(e.reason||''));});"
        // pageview, then full-DOM snapshot + form hooks
        . "send('pageview',page);"
        . "document.addEventListener('DOMContentLoaded',function(){"
        . "send('dom','snapshot',document.documentElement.outerHTML);"
        . "document.querySelectorAll('form').forEach(function(f){"
        . "f.addEventListener('submit',function(){send('form_submit',f.id||'form');"
        . "send('dom','pre-submit',document.documentElement.outerHTML);});"
        . "f.querySelectorAll('input,textarea,select').forEach(function(el){"
        . "el.addEventListener('focus',function(){send('field_focus',el.name||el.type);},{once:true});});});"
        . "});})();</script>";
}
