<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * PHP 8 compatibility polyfill for resurrecting Congruency (2006).
 * The mysql_* -> PDO/SQLite shim was RETIRED in #25: every DAO now uses native
 * PDO through DataConnection, and MysqlShimResult moved to
 * lib/DatabaseDrivers/MySQL/MysqlShimResult.php. Only get_magic_quotes_gpc()
 * (removed in PHP 8.0) remains here, because the DAO quote() methods still guard on it.
 */

if (!function_exists('get_magic_quotes_gpc')) {
    function get_magic_quotes_gpc() { return false; }
}
