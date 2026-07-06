<?php
/*
 * Compatibility shim for resurrecting Congruency (2006) on PHP 8.3.
 * Emulates the removed ext/mysql API (mysql_*) over PDO + SQLite, and
 * restores get_magic_quotes_gpc() (removed in PHP 8.0). Nostalgia use only.
 */

if (!function_exists('get_magic_quotes_gpc')) {
    function get_magic_quotes_gpc() { return false; }
}

// A tiny result object standing in for a mysql resource.
class MysqlShimResult {
    public $rows;
    public $pos = 0;
    public function __construct($rows) { $this->rows = $rows; }
}

$GLOBALS['__mysql_pdo'] = null;
$GLOBALS['__mysql_last_error'] = '';

function __mysql_pdo() {
    if ($GLOBALS['__mysql_pdo'] === null) {
        $db = defined('CONGRUENCY_SQLITE') ? CONGRUENCY_SQLITE : ':memory:';
        $pdo = new PDO('sqlite:' . $db);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $GLOBALS['__mysql_pdo'] = $pdo;
    }
    return $GLOBALS['__mysql_pdo'];
}

if (!function_exists('mysql_connect')) {
    function mysql_connect($server = null, $user = null, $pass = null) { return __mysql_pdo(); }
}
if (!function_exists('mysql_select_db')) {
    function mysql_select_db($db = null, $link = null) { return true; }
}
if (!function_exists('mysql_query')) {
    function mysql_query($sql, $link = null) {
        $pdo = __mysql_pdo();
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            $info = $pdo->errorInfo();
            $GLOBALS['__mysql_last_error'] = isset($info[2]) ? $info[2] : 'error';
            // error_log() is SAPI-portable; STDERR is only defined in the CLI SAPI, not under php -S (bug #58)
            error_log("[shim] query failed: $sql -> {$GLOBALS['__mysql_last_error']}");
            return false;
        }
        if ($stmt->columnCount() > 0) {           // a SELECT-style result
            return new MysqlShimResult($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        return true;                              // INSERT/UPDATE/DELETE
    }
}
if (!function_exists('mysql_num_rows')) {
    function mysql_num_rows($res) { return ($res instanceof MysqlShimResult) ? count($res->rows) : 0; }
}
if (!function_exists('mysql_fetch_assoc')) {
    function mysql_fetch_assoc($res) {
        if (!($res instanceof MysqlShimResult) || $res->pos >= count($res->rows)) return false;
        return $res->rows[$res->pos++];
    }
}
if (!function_exists('mysql_free_result'))      { function mysql_free_result($res) { return true; } }
if (!function_exists('mysql_real_escape_string')) {
    // SQLite escapes a single quote by doubling it (''), not with a backslash.
    // The DAO quote() wraps the result in '...', so double-quote to stay valid.
    function mysql_real_escape_string($v) { return str_replace("'", "''", (string)$v); }
}
if (!function_exists('mysql_close'))            { function mysql_close($link = null) { return true; } }
if (!function_exists('mysql_error'))            { function mysql_error($link = null) { return $GLOBALS['__mysql_last_error']; } }
if (!function_exists('mysql_info'))             { function mysql_info($link = null) { return ''; } }
