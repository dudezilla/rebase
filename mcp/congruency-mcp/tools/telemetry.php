<?php
/*
 * telemetry.php <db> [verdictFilter|all] [limit]
 * Reads the attack-stack table written by the harness telemetry endpoint and
 * emits JSON on stdout. Used by the congruency MCP's query_telemetry tool.
 */
$db    = $argv[1] ?? '';
$kind  = $argv[2] ?? 'all';
$limit = (int)($argv[3] ?? 20);
if ($limit <= 0 || $limit > 200) $limit = 20;

try {
    $pdo = new PDO('sqlite:' . $db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $where = '';
    $params = [];
    if ($kind !== '' && strtolower($kind) !== 'all') {
        $where = 'WHERE UPPER(verdict) = ?';
        $params[] = strtoupper($kind);
    }
    $sql = "SELECT id,iteration,name,page,vector,prediction,result,evidence,verdict,predicted_ts,result_ts
            FROM attacks $where ORDER BY id DESC LIMIT " . $limit;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // Verdict tally over the whole table (not just the page returned).
    $tally = [];
    foreach ($pdo->query("SELECT UPPER(verdict) v, COUNT(*) c FROM attacks GROUP BY UPPER(verdict)") as $r) {
        $tally[$r['v'] ?: 'PENDING'] = (int)$r['c'];
    }

    echo json_encode(['ok' => true, 'count' => count($rows), 'tally' => $tally, 'rows' => $rows]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
