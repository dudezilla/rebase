<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * Result-set value object returned by DataConnection::query() for SELECTs.
 * Relocated here from boot/shim.php when the mysql_* shim was retired (#25).
 * DAOs read ->rows (an array of associative rows) directly.
 */
if (!class_exists('MysqlShimResult')) {
    class MysqlShimResult {
        public $rows;
        public $pos = 0;
        public function __construct($rows) { $this->rows = $rows; }
    }
}
?>
