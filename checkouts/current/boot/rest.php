<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * rest.php — a generic READ-ONLY REST interface over every table in the unified
 * DB (CONGRUENCY_SQLITE). Dispatched by boot/router.php when ?api is present:
 *
 *   ?api=tables              -> { "tables": [ ... ] }        (discovery)
 *   ?api=<table>             -> { table,total,page,per,pages,rows:[...] }   (paginated)
 *   ?api=<table>&p=2&per=25  -> page 2, 25 rows
 *   ?api=<table>&id=<pk>     -> a single row by primary key
 *
 * The table name is always validated against sqlite_master before use (allowlist),
 * so it can't be used for injection. Writes (POST/PUT/DELETE) are intentionally NOT
 * implemented yet — see ticket #47.
 */
function congruency_rest_dispatch() {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    $out = null;
    try {
        if (!defined('CONGRUENCY_SQLITE')) { throw new Exception('CONGRUENCY_SQLITE not defined'); }
        $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tables = array();
        foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $r) {
            $tables[$r['name']] = 1;
        }

        $api = (string)($_GET['api'] ?? '');
        if ($api === '' || $api === 'tables') {
            $out = array('tables' => array_keys($tables));
        } elseif (isset($tables[$api])) {
            // primary-key column (for ?id=)
            $pk = null;
            foreach ($db->query("PRAGMA table_info(\"$api\")") as $c) { if ($c['pk']) { $pk = $c['name']; break; } }

            if (isset($_GET['id']) && $pk !== null) {
                $st = $db->prepare("SELECT * FROM \"$api\" WHERE \"$pk\" = ?");
                $st->execute(array($_GET['id']));
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row === false) { http_response_code(404); $out = array('error' => 'not found', 'table' => $api, 'id' => $_GET['id']); }
                else { $out = array('table' => $api, 'pk' => $pk, 'row' => $row); }
            } else {
                $per = isset($_GET['per']) ? max(1, min(500, (int)$_GET['per'])) : 50;
                $pg  = isset($_GET['p'])   ? max(1, (int)$_GET['p']) : 1;
                $total = (int)$db->query("SELECT COUNT(*) FROM \"$api\"")->fetchColumn();
                $off = ($pg - 1) * $per;
                $rows = $db->query("SELECT * FROM \"$api\" LIMIT $per OFFSET $off")->fetchAll(PDO::FETCH_ASSOC);
                $out = array('table' => $api, 'pk' => $pk, 'total' => $total, 'page' => $pg, 'per' => $per,
                             'pages' => (int)ceil($total / max(1, $per)), 'rows' => $rows);
            }
        } else {
            http_response_code(404);
            $out = array('error' => 'unknown table', 'table' => $api, 'tables' => array_keys($tables));
        }
    } catch (\Throwable $e) {
        http_response_code(500);
        $out = array('error' => $e->getMessage());
    }
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    return true;
}
