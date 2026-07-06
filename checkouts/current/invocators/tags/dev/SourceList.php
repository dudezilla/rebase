<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * SourceList — the CMS browsing its own running source (ticket: self-hosting).
 *
 * Admin-only (UserPrivilegeSet::logged_in()). The source is mirrored into the DB by
 * tools/ingest_self.py on every crank, content-addressed by git blob hash: code_blobs(hash,...)
 * holds deduped content, code_refs(hash,path,commit_sha,is_current) is the reverse lookup.
 *   ?page=source            -> index of the RUNNING source (code_refs.is_current=1), paginated (?p=)
 *   ?page=source&file=<hash> -> one blob: escaped <pre> + line numbers, its path/commit refs, version history
 * Every value is esc()'d — output flows back through execute_all_tags, so any '<<<...>>>' in source
 * must stay literal.
 */
if (!class_exists("SourceList")) {
    class SourceList implements Tag_Interface {

        private $arguments;
        public function __construct($arguments) { $this->arguments = $arguments; }
        private static function esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }

        private static function gate() {
            if (!class_exists('UserPrivilegeSet') || !UserPrivilegeSet::logged_in()) {
                return "<p><strong>Administrator area.</strong> Please log in to browse the source.</p>\n<<<Login>>>";
            }
            return null;
        }

        public function get_document() {
            $deny = self::gate();
            if ($deny !== null) { return $deny; }
            try {
                if (!defined('CONGRUENCY_SQLITE')) { return "<p>SourceList: no DB.</p>"; }
                $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $hash = isset($_GET['file']) ? trim((string) $_GET['file']) : '';
                if ($hash !== '') { return $this->view($db, $hash); }
                return $this->index($db);
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>SourceList error: " . self::esc($e->getMessage()) . "</div>";
            }
        }

        private function index($db) {
            $rows = $db->query(
                "SELECT r.path, r.hash, b.lang, b.bytes FROM code_refs r JOIN code_blobs b ON b.hash=r.hash "
                . "WHERE r.is_current=1 ORDER BY r.path")->fetchAll(PDO::FETCH_ASSOC);
            $total = count($rows);
            $per = 40; $pages = max(1, (int) ceil($total / $per));
            $pg = isset($_GET['p']) ? (int) $_GET['p'] : 1;
            if ($pg < 1) { $pg = 1; } if ($pg > $pages) { $pg = $pages; }
            $slice = array_slice($rows, ($pg - 1) * $per, $per);

            $pager = "<div class='pager'>"
                   . ($pg > 1 ? "<a href='?page=source&p=" . ($pg - 1) . "'>&larr; prev</a>" : "<span>&larr; prev</span>")
                   . " page <strong>$pg</strong> of $pages ($total files) "
                   . ($pg < $pages ? "<a href='?page=source&p=" . ($pg + 1) . "'>next &rarr;</a>" : "<span>next &rarr;</span>")
                   . "</div>\n";

            $out = "<p>The CMS's own running source, addressed by git blob hash.</p>\n" . $pager . "<ul class='srclist'>\n";
            foreach ($slice as $r) {
                $out .= "<li><a href='?page=source&file=" . self::esc($r['hash']) . "'>" . self::esc($r['path']) . "</a> "
                      . "<span style='color:#888;font-size:.85em'>" . self::esc($r['lang']) . " &middot; " . (int) $r['bytes'] . " B</span></li>\n";
            }
            return $out . "</ul>\n" . $pager;
        }

        private function view($db, $hash) {
            $st = $db->prepare("SELECT lang, bytes, body FROM code_blobs WHERE hash=?");
            $st->execute(array($hash));
            $blob = $st->fetch(PDO::FETCH_ASSOC);
            if (!$blob) { return "<p>No such source blob <code>" . self::esc($hash) . "</code>. <a href='?page=source'>&larr; index</a></p>"; }

            // reverse lookup: which path(s)/commit(s) this blob appeared at
            $rs = $db->prepare("SELECT DISTINCT path, commit_sha, is_current FROM code_refs WHERE hash=? ORDER BY path");
            $rs->execute(array($hash));
            $refs = $rs->fetchAll(PDO::FETCH_ASSOC);
            $path = $refs ? $refs[0]['path'] : '';

            // version history of that path (all blobs, newest ts first)
            $hs = $db->prepare("SELECT hash, MIN(commit_sha) c, MAX(ts) t, MAX(is_current) cur FROM code_refs "
                             . "WHERE path=? GROUP BY hash ORDER BY t DESC");
            $hs->execute(array($path));
            $hist = $hs->fetchAll(PDO::FETCH_ASSOC);

            $head = "<p><a href='?page=source'>&larr; index</a> &middot; <strong>" . self::esc($path) . "</strong> "
                  . "<span style='color:#888'>(" . self::esc($blob['lang']) . " &middot; " . (int) $blob['bytes'] . " B &middot; blob " . self::esc(substr($hash, 0, 12)) . ")</span></p>\n";

            $refhtml = "<p style='font-size:.85em;color:#666'>appears at: ";
            foreach ($refs as $r) {
                $refhtml .= self::esc($r['path']) . "@" . self::esc(substr($r['commit_sha'], 0, 9)) . ($r['is_current'] ? " <em>(current)</em>" : "") . " &nbsp; ";
            }
            $refhtml .= "</p>\n";

            $verhtml = "";
            if (count($hist) > 1) {
                $verhtml = "<p style='font-size:.85em'>versions of this file: ";
                foreach ($hist as $h) {
                    $sel = ($h['hash'] === $hash);
                    $lab = self::esc(substr($h['hash'], 0, 9)) . ($h['cur'] ? "*" : "");
                    $verhtml .= $sel ? "<strong>$lab</strong> " : "<a href='?page=source&file=" . self::esc($h['hash']) . "'>$lab</a> ";
                }
                $verhtml .= "</p>\n";
            }

            $esc = self::esc($blob['body']);
            $lines = explode("\n", $esc);
            $code = "<pre style='background:#f4f1e8;border:1px solid #d8d2c4;padding:.6rem;overflow:auto;font-size:.82rem;line-height:1.4'>";
            $n = 1;
            foreach ($lines as $ln) {
                $code .= "<span style='color:#b0a890;user-select:none'>" . sprintf('%4d', $n++) . "</span>  " . $ln . "\n";
            }
            $code .= "</pre>\n";

            return $head . $refhtml . $verhtml . $code;
        }
    }
}
?>
