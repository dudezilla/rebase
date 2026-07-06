<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * Annotations — one browse view over the abstract tag->target layer.
 *
 * The `annotations` table is the unified tagging model: a `tag` applied to an opaque `target` ref
 * ("source:<hash>", "page:<DocumentID>", "doc:<hash>", "ticket:<id>"). Source `flag`s and page
 * categories both live here. This tag renders them all, with a tag/kind filter bar; each target is
 * linked back to wherever it lives.
 *   ?page=annotations             -> everything, newest first
 *   ?page=annotations&tag=flag    -> one tag
 *   ?page=annotations&kind=page   -> one target-kind (source|page|doc|ticket)
 */
if (!class_exists("Annotations")) {
    class Annotations implements Tag_Interface {

        private $arguments;
        public function __construct($arguments) { $this->arguments = $arguments; }
        private static function esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }

        private static function target_link($target) {
            $pos = strpos((string) $target, ':');
            if ($pos === false) { return self::esc($target); }
            $kind = substr($target, 0, $pos);
            $id   = substr($target, $pos + 1);
            switch ($kind) {
                case 'source': return "<a href='?page=source&file=" . self::esc($id) . "'>source: " . self::esc(substr($id, 0, 12)) . "</a>";
                case 'doc':    return "<a href='?page=docs&doc=" . self::esc($id) . "'>doc: " . self::esc(substr($id, 0, 12)) . "</a>";
                case 'page':   return "<a href='?page=" . self::esc($id) . "'>page: " . self::esc($id) . "</a>";
                case 'ticket': return "<a href='?page=tickets'>ticket #" . self::esc($id) . "</a>";
                default:       return self::esc($target);
            }
        }

        public function get_document() {
            try {
                if (!defined('CONGRUENCY_SQLITE')) { return "<p>Annotations: no DB.</p>"; }
                $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                if (!$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='annotations'")->fetch()) {
                    return "<p>No annotations yet. Flag a source file on <a href='?page=source'>Source</a> or tag a page on <a href='?page=pages'>Pages</a>.</p>";
                }

                $tag  = isset($_GET['tag'])  ? trim((string) $_GET['tag'])  : '';
                $kind = isset($_GET['kind']) ? trim((string) $_GET['kind']) : '';

                $tags = $db->query("SELECT tag, COUNT(*) n FROM annotations GROUP BY tag ORDER BY tag")->fetchAll(PDO::FETCH_ASSOC);
                $bar = "<p style='font-size:.9rem'><strong>tag:</strong> " . ($tag === '' ? "<strong>all</strong>" : "<a href='?page=annotations'>all</a>") . " ";
                foreach ($tags as $t) {
                    $lab = self::esc($t['tag']) . " (" . (int) $t['n'] . ")";
                    $bar .= "&middot; " . (($t['tag'] === $tag) ? "<strong>$lab</strong>" : "<a href='?page=annotations&tag=" . self::esc($t['tag']) . "'>$lab</a>") . " ";
                }
                $bar .= "<br><strong>kind:</strong> " . ($kind === '' ? "<strong>all</strong>" : "<a href='?page=annotations'>all</a>") . " ";
                foreach (array('source', 'page', 'doc', 'ticket') as $k) {
                    $bar .= "&middot; " . (($k === $kind) ? "<strong>$k</strong>" : "<a href='?page=annotations&kind=$k'>$k</a>") . " ";
                }
                $bar .= "</p>\n";

                $where = array(); $args = array();
                if ($tag !== '')  { $where[] = "tag=?";         $args[] = $tag; }
                if ($kind !== '') { $where[] = "target LIKE ?"; $args[] = $kind . ':%'; }
                $sql = "SELECT tag, target, note, ts FROM annotations" . ($where ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY ts DESC, id DESC";
                $st = $db->prepare($sql);
                $st->execute($args);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                if (!$rows) { return $bar . "<p>No annotations match that filter.</p>"; }

                $out = $bar . "<table style='border-collapse:collapse;font-size:.9rem'>\n"
                     . "<tr><th align='left' style='padding-right:1rem'>tag</th><th align='left' style='padding-right:1rem'>target</th>"
                     . "<th align='left' style='padding-right:1rem'>note</th><th align='left'>when</th></tr>\n";
                foreach ($rows as $r) {
                    $when = $r['ts'] ? date('Y-m-d H:i', (int) $r['ts']) : '';
                    $out .= "<tr><td style='padding:.15rem 1rem .15rem 0'><code>" . self::esc($r['tag']) . "</code></td>"
                          . "<td style='padding:.15rem 1rem .15rem 0'>" . self::target_link($r['target']) . "</td>"
                          . "<td style='padding:.15rem 1rem .15rem 0'>" . self::esc($r['note']) . "</td>"
                          . "<td style='padding:.15rem 0;color:#888'>" . self::esc($when) . "</td></tr>\n";
                }
                return $out . "</table>\n";
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>Annotations error: " . self::esc($e->getMessage()) . "</div>";
            }
        }
    }
}
?>
