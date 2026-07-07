<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * CategoryPages — browse pages by category (the page-tagging layer).
 *
 * Invoked as <<<CategoryPages(specifications)>>>. Resolves the category name to
 * its Categories.key, then lists every Document linked to it via the
 * Page_Categories join table, as hyperlinks. With no argument it lists all
 * categories and how many pages each holds.
 */
if (!class_exists("CategoryPages")) {
    class CategoryPages implements Tag_Interface {

        private $category;
        public function __construct($arguments) {
            $this->category = (isset($arguments) && method_exists($arguments, 'top')) ? $arguments->top() : '';
        }
        private static function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

        private function db() {
            $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $db;
        }

        public function get_document() {
            try {
                if (!defined('CONGRUENCY_SQLITE')) { return "<p>CategoryPages: no DB.</p>"; }
                $db = $this->db();
                // category comes from the tag argument, else the ?category= URL param (clickable index)
                $cat = trim((string)$this->category);
                if ($cat === '' && isset($_GET['category'])) { $cat = trim((string)$_GET['category']); }

                if ($cat === '') {   // no arg -> index of all categories with page counts
                    $rows = $db->query("SELECT c.name, c.description, COUNT(a.id) AS n
                        FROM Categories c LEFT JOIN annotations a ON a.tag=c.name AND a.target LIKE 'page:%'
                        GROUP BY c.`key` ORDER BY c.name")->fetchAll(PDO::FETCH_ASSOC);
                    $out = "<ul>";
                    foreach ($rows as $r) {
                        $out .= "<li><a href='?page=pages&category=" . self::esc($r['name']) . "'>" . self::esc($r['name'])
                              . "</a> (" . (int)$r['n'] . ")" . ($r['description'] ? " &mdash; " . self::esc($r['description']) : "") . "</li>";
                    }
                    return $out . "</ul>";
                }

                $st = $db->prepare("SELECT d.DocumentID, d.Title FROM annotations a
                    JOIN Documents d ON d.DocumentID=substr(a.target, 6)
                    WHERE a.tag=? AND a.target LIKE 'page:%' ORDER BY d.DocumentID");
                $st->execute(array($cat));
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                if (!$rows) { return "<p>No pages tagged <code>" . self::esc($cat) . "</code> yet.</p>"; }
                $out = "<ul>";
                foreach ($rows as $r) {
                    $title = ($r['Title'] !== null && $r['Title'] !== '') ? $r['Title'] : $r['DocumentID'];
                    $out .= "<li><a href='?page=" . self::esc($r['DocumentID']) . "'>" . self::esc($title) . "</a></li>";
                }
                return $out . "</ul>";
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>CategoryPages error: " . self::esc(get_class($e) . ': ' . $e->getMessage()) . "</div>";
            }
        }
    }
}
?>
