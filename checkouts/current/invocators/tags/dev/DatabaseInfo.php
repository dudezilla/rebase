<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * DatabaseInfo — a self-describing view of the unified DB (?page=database): every table, its row count,
 * and a link to browse it via the REST API (?api=<table>). The DB backs the whole site — pages, tags,
 * forms, tickets, annotations, and the self-hosting source/doc archive — so this makes it inspectable
 * from inside the CMS. Denylisted (admin-only) tables are marked, not linked.
 */
if (!class_exists("DatabaseInfo")) {
    class DatabaseInfo implements Tag_Interface {

        public function __construct($arguments) {}
        private static function esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }

        public function get_document() {
            try {
                if (!defined('CONGRUENCY_SQLITE')) { return "<p>DatabaseInfo: no DB.</p>"; }
                $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $denied = array('code_blobs' => 1, 'code_refs' => 1, 'doc_blobs' => 1, 'doc_refs' => 1,
                                'Login_Password' => 1, 'User_Group_Mappings' => 1, 'Group_Privileges' => 1);
                $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
                              ->fetchAll(PDO::FETCH_COLUMN);

                $out = "<p>The unified SQLite database (<code>CONGRUENCY_SQLITE</code>) that backs the whole site — "
                     . "every page, tag, form, ticket, and the self-hosting source/doc archive. Reads are public via "
                     . "<code>?api=&lt;table&gt;</code>; writes need the admin login. Admin-only tables (the archive + "
                     . "auth) are denylisted from REST.</p>\n";
                $out .= "<table>\n<tr><th style='padding-right:1.5rem'>table</th><th style='padding-right:1.5rem'>rows</th>"
                      . "<th>browse</th></tr>\n";
                $total = 0;
                foreach ($tables as $t) {
                    $n = (int) $db->query('SELECT COUNT(*) FROM "' . $t . '"')->fetchColumn();
                    $total += $n;
                    $browse = isset($denied[$t])
                        ? "<span style='color:#999'>admin-only (not in REST)</span>"
                        : "<a href='?api=" . self::esc($t) . "&per=25'>?api=" . self::esc($t) . "</a>";
                    $out .= "<tr><td style='padding-right:1.5rem'><code>" . self::esc($t) . "</code></td>"
                          . "<td style='padding-right:1.5rem'>" . number_format($n) . "</td><td>" . $browse . "</td></tr>\n";
                }
                $out .= "</table>\n";
                $out .= "<p style='color:#888;font-size:.9rem;margin-top:1rem'>" . count($tables) . " tables, "
                     . number_format($total) . " rows total. Export a git-viewable seed with "
                     . "<code>tools/db_export.py</code> &rarr; <code>state/seed.sql</code>; rebuild with "
                     . "<code>tools/db_import.py --to &lt;path&gt;</code>.</p>\n";
                return $out;
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>DatabaseInfo error: " . self::esc($e->getMessage()) . "</div>";
            }
        }
    }
}
?>
