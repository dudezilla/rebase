<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * DocList — the CMS browsing its own documentation (ticket: self-hosting).
 *
 * Admin-only (UserPrivilegeSet::logged_in()). Docs are mirrored into the DB by tools/ingest_self.py,
 * content-addressed by git blob hash: doc_blobs(hash,kind,bytes,body) + doc_refs(hash,path,commit_sha,is_current).
 *   ?page=docs           -> index of the CURRENT docs (doc_refs.is_current=1)
 *   ?page=docs&doc=<hash> -> one doc rendered via a minimal, ESCAPE-FIRST markdown subset (any '<<<Tag>>>'
 *                            example in the doc stays literal, so the tag engine never re-parses it)
 */
if (!class_exists("DocList")) {
    class DocList implements Tag_Interface {

        private $arguments;
        public function __construct($arguments) { $this->arguments = $arguments; }
        private static function esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }

        private static function gate() {
            if (!class_exists('UserPrivilegeSet') || !UserPrivilegeSet::logged_in()) {
                return "<p><strong>Administrator area.</strong> Please log in to browse the documentation.</p>\n<<<Login>>>";
            }
            return null;
        }

        public function get_document() {
            $deny = self::gate();
            if ($deny !== null) { return $deny; }
            try {
                if (!defined('CONGRUENCY_SQLITE')) { return "<p>DocList: no DB.</p>"; }
                $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $hash = isset($_GET['doc']) ? trim((string) $_GET['doc']) : '';
                if ($hash !== '') { return $this->view($db, $hash); }
                return $this->index($db);
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>DocList error: " . self::esc($e->getMessage()) . "</div>";
            }
        }

        private function index($db) {
            $rows = $db->query(
                "SELECT r.path, r.hash, b.kind, b.bytes FROM doc_refs r JOIN doc_blobs b ON b.hash=r.hash "
                . "WHERE r.is_current=1 ORDER BY r.path")->fetchAll(PDO::FETCH_ASSOC);
            $out = "<p>The CMS's own documentation, addressed by git blob hash.</p>\n<ul class='doclist'>\n";
            foreach ($rows as $r) {
                $out .= "<li><a href='?page=docs&doc=" . self::esc($r['hash']) . "'>" . self::esc($r['path']) . "</a> "
                      . "<span style='color:#888;font-size:.85em'>" . self::esc($r['kind']) . " &middot; " . (int) $r['bytes'] . " B</span></li>\n";
            }
            return $out . "</ul>\n";
        }

        private function view($db, $hash) {
            $st = $db->prepare("SELECT kind, bytes, body FROM doc_blobs WHERE hash=?");
            $st->execute(array($hash));
            $blob = $st->fetch(PDO::FETCH_ASSOC);
            if (!$blob) { return "<p>No such doc blob <code>" . self::esc($hash) . "</code>. <a href='?page=docs'>&larr; index</a></p>"; }

            $rs = $db->prepare("SELECT DISTINCT path, is_current FROM doc_refs WHERE hash=? ORDER BY path");
            $rs->execute(array($hash));
            $refs = $rs->fetchAll(PDO::FETCH_ASSOC);
            $path = $refs ? $refs[0]['path'] : '';

            $hs = $db->prepare("SELECT hash, MAX(ts) t, MAX(is_current) cur FROM doc_refs WHERE path=? GROUP BY hash ORDER BY t DESC");
            $hs->execute(array($path));
            $hist = $hs->fetchAll(PDO::FETCH_ASSOC);

            $head = "<p><a href='?page=docs'>&larr; index</a> &middot; <strong>" . self::esc($path) . "</strong> "
                  . "<span style='color:#888'>(blob " . self::esc(substr($hash, 0, 12)) . ")</span></p>\n";
            $verhtml = "";
            if (count($hist) > 1) {
                $verhtml = "<p style='font-size:.85em'>versions: ";
                foreach ($hist as $h) {
                    $lab = self::esc(substr($h['hash'], 0, 9)) . ($h['cur'] ? "*" : "");
                    $verhtml .= ($h['hash'] === $hash) ? "<strong>$lab</strong> " : "<a href='?page=docs&doc=" . self::esc($h['hash']) . "'>$lab</a> ";
                }
                $verhtml .= "</p>\n";
            }
            $rendered = ($blob['kind'] === 'markdown')
                ? self::markdown($blob['body'])
                : "<pre style='background:#f4f1e8;padding:.6rem;overflow:auto'>" . self::esc($blob['body']) . "</pre>";
            return $head . $verhtml . "<div class='doc'>\n" . $rendered . "</div>\n";
        }

        /* Minimal markdown subset. ESCAPE FIRST (so HTML and '<<<Tag>>>' examples stay literal), then apply:
           #.. -> h1-h6, blank-line paragraphs, ``` / 4-space indent -> <pre><code>, `x` -> <code>, [t](u) -> <a>. */
        private static function markdown($text) {
            $esc = htmlspecialchars((string) $text, ENT_QUOTES);
            $lines = explode("\n", str_replace("\r", "", $esc));
            $out = ""; $para = array(); $inFence = false; $code = "";
            $flush = function () use (&$para, &$out) {
                if ($para) { $out .= "<p>" . implode(" ", $para) . "</p>\n"; $para = array(); }
            };
            foreach ($lines as $ln) {
                if (preg_match('/^\s*```/', $ln)) {
                    if (!$inFence) { $flush(); $inFence = true; $code = ""; }
                    else { $out .= "<pre><code>" . $code . "</code></pre>\n"; $inFence = false; }
                    continue;
                }
                if ($inFence) { $code .= $ln . "\n"; continue; }
                if (preg_match('/^(#{1,6})\s+(.*)$/', $ln, $m)) {
                    $flush(); $h = strlen($m[1]); $out .= "<h$h>" . self::inline($m[2]) . "</h$h>\n"; continue;
                }
                if (preg_match('/^    (.*)$/', $ln, $m)) {          // 4-space indented code line
                    $flush(); $out .= "<pre><code>" . $m[1] . "</code></pre>\n"; continue;
                }
                if (trim($ln) === "") { $flush(); continue; }
                $para[] = self::inline($ln);
            }
            $flush();
            if ($inFence) { $out .= "<pre><code>" . $code . "</code></pre>\n"; }
            return $out;
        }

        private static function inline($s) {
            $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
            $s = preg_replace('/\[([^\]]+)\]\(([^)\s]+)\)/', '<a href="$2">$1</a>', $s);
            return $s;
        }
    }
}
?>
