<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * MemoryList — a TOP-LEVEL tag: the controller (Claude) tool-use log, reflecting
 * the state of the MCP. Every call routed through the gate is stamped
 * {ts, session, tool, intent} — often by Claude — and viewed here in REVERSE
 * CHRONOLOGICAL order.
 *
 * Source of truth is two-fold and merged so the view is always current:
 *   1. the durable `memories` table in the unified DB (where the gate persists), and
 *   2. the live gate log ~/.MCP/gate-memories.json (what the RUNNING gate is writing
 *      right now, before it has been restarted onto the DB path).
 * Rows are de-duped on (ts, tool) and sorted newest-first.
 */
if (!class_exists("MemoryList")) {
    class MemoryList implements Tag_Interface {

        private $arguments;
        public function __construct($arguments) { $this->arguments = $arguments; }
        private static function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

        private function collect() {
            $rows = array();
            // 1) durable DB projection
            if (defined('CONGRUENCY_SQLITE')) {
                try {
                    $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
                    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    foreach ($db->query("SELECT ts, session, tool, intent FROM memories") as $r) {
                        $rows[] = array('ts' => (string)$r['ts'], 'session' => $r['session'],
                                        'tool' => (string)$r['tool'], 'intent' => (string)$r['intent'], 'src' => 'db');
                    }
                } catch (\Throwable $e) { /* ignore, fall through to the live log */ }
            }
            // 2) the live MCP gate log (the running controller)
            $home = getenv('HOME');
            $log = $home ? rtrim($home, '/') . '/.MCP/gate-memories.json' : '';
            if ($log && is_readable($log)) {
                $data = json_decode((string)@file_get_contents($log), true);
                if (is_array($data)) {
                    foreach ($data as $e) {
                        $rows[] = array('ts' => (string)($e['timestamp'] ?? ''), 'session' => $e['session'] ?? null,
                                        'tool' => (string)($e['tool'] ?? ''),
                                        'intent' => (string)($e['intent'] ?? ($e['note'] ?? '')), 'src' => 'mcp');
                    }
                }
            }
            // de-dupe on (ts, tool); prefer the DB row (seen first)
            $seen = array(); $uniq = array();
            foreach ($rows as $r) {
                $k = $r['ts'] . '|' . $r['tool'];
                if (!isset($seen[$k])) { $seen[$k] = 1; $uniq[] = $r; }
            }
            // reverse chronological
            usort($uniq, function ($a, $b) { return strcmp($b['ts'], $a['ts']); });
            return $uniq;
        }

        public function get_document() {
            try {
                $rows = $this->collect();
                $sessions = array();
                foreach ($rows as $r) { if ($r['session'] !== null && $r['session'] !== '') { $sessions[$r['session']] = 1; } }

                // pagination (reverse chronological; page 1 = newest)
                $total = count($rows);
                $per   = 10;
                $pages = max(1, (int)ceil($total / $per));
                $pg    = isset($_GET['p']) ? (int)$_GET['p'] : 1;
                if ($pg < 1) { $pg = 1; }
                if ($pg > $pages) { $pg = $pages; }
                $slice = array_slice($rows, ($pg - 1) * $per, $per);

                $out  = "<style>.mem{display:grid;gap:.55rem}"
                      . ".m{border:1px solid #d8d2c4;border-left:5px solid #8a5a1a;border-radius:6px;padding:.5rem .8rem;background:#fffdf8}"
                      . ".m .top{font:.7rem/1.4 monospace;color:#666}.m .tool{color:#8a5a1a;font-weight:600}"
                      . ".m .src{float:right;font:600 .58rem/1.6 sans-serif;text-transform:uppercase;color:#fff;background:#8a7a1a;padding:.05rem .4rem;border-radius:3px}"
                      . ".m .intent{margin:.25rem 0 0;font-size:.9rem;color:#333}"
                      . ".pager{margin:1rem 0;font-size:.85rem}.pager a{margin:0 .5rem}</style>\n";
                $out .= "<p style='font-size:.85rem;color:#555'><strong>" . $total . "</strong> controller actions &middot; <strong>"
                      . count($sessions) . "</strong> session(s) &middot; reverse chronological, reflecting the MCP.</p>\n";

                $pager = "<div class='pager'>";
                $pager .= ($pg > 1) ? "<a href='?page=memories&p=" . ($pg - 1) . "'>&larr; newer</a>" : "<span style='color:#aaa'>&larr; newer</span>";
                $pager .= " page <strong>$pg</strong> of $pages ";
                $pager .= ($pg < $pages) ? "<a href='?page=memories&p=" . ($pg + 1) . "'>older &rarr;</a>" : "<span style='color:#aaa'>older &rarr;</span>";
                $pager .= "</div>\n";

                $out .= $pager . "<div class='mem'>\n";
                foreach ($slice as $r) {
                    $out .= "  <div class='m'><div class='top'><span class='src'>" . self::esc($r['src']) . "</span>"
                          . self::esc($r['ts']) . " &middot; session " . self::esc($r['session'] !== null ? $r['session'] : '-')
                          . " &middot; <span class='tool'>" . self::esc($r['tool']) . "</span></div>"
                          . "<div class='intent'>" . self::esc($r['intent']) . "</div></div>\n";
                }
                $out .= "</div>\n" . $pager;
                return $out;
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>MemoryList error: " . self::esc(get_class($e) . ': ' . $e->getMessage()) . "</div>";
            }
        }
    }
}
?>
