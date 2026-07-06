<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * PageList — render every page as a hyperlink (the ?page=pages index).
 *
 * Implements Tag_Interface; invoked as <<<PageList>>>. Reads all Documents
 * from CONGRUENCY_SQLITE and lists them (title + id + description) as links.
 */
if (!class_exists("PageList")) {
    class PageList implements Tag_Interface {

        private $arguments;
        public function __construct($arguments) { $this->arguments = $arguments; }
        private static function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

        public function get_document() {
            try {
                if (!defined('CONGRUENCY_SQLITE')) { return "<p>PageList: no DB.</p>"; }
                $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $rows = $db->query("SELECT DocumentID, Title, Description FROM Documents ORDER BY DocumentID")->fetchAll(PDO::FETCH_ASSOC);
                $out  = "<style>.pglist{display:grid;gap:.5rem;list-style:none;padding:0}"
                      . ".pglist li{border:1px solid #d8d2c4;border-left:5px solid #8a5a1a;border-radius:6px;padding:.5rem .8rem;background:#fffdf8}"
                      . ".pglist a{font-size:1.05rem}.pglist .id{font:.72rem/1 monospace;color:#777;margin-left:.4rem}"
                      . ".pglist p{margin:.2rem 0 0;font-size:.85rem;color:#555}</style>\n";
                $out .= "<p style='font-size:.85rem;color:#555'><strong>" . count($rows) . "</strong> pages</p>\n";
                $out .= "<ul class='pglist'>\n";
                foreach ($rows as $r) {
                    $id = $r['DocumentID'];
                    $title = ($r['Title'] !== null && $r['Title'] !== '') ? $r['Title'] : $id;
                    $out .= "  <li><a href='?page=" . self::esc($id) . "'>" . self::esc($title) . "</a>"
                          . "<span class='id'>?page=" . self::esc($id) . "</span>";
                    if ($r['Description']) { $out .= "<p>" . self::esc($r['Description']) . "</p>"; }
                    $out .= "</li>\n";
                }
                $out .= "</ul>\n";
                return $out;
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>PageList error: " . self::esc(get_class($e) . ': ' . $e->getMessage()) . "</div>";
            }
        }
    }
}
?>
