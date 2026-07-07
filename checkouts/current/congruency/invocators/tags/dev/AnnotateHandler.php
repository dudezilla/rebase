<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * AnnotateHandler — apply an annotation (the handler for AnnotateForm), on ?page=annotateDone.
 *
 * The general front door to the tag->target layer. Reads the completed form via FORM_MANAGER, validates
 * kind in {source,page,doc,ticket} plus a non-empty id and tag, inserts into annotations (tag -> target
 * "kind:id"), and resets the form. The inline source `flag` and the page-category tagger are just two
 * presets of this same write.
 */
if (!class_exists("AnnotateHandler")) {
    class AnnotateHandler implements Tag_Interface {

        private $arguments;
        public function __construct($arguments) { $this->arguments = $arguments; }
        private static function esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
        private static function back() { return "<p><a href='?page=forms'>&larr; back to the admin forms</a></p>"; }

        public function get_document() {
            try {
                $kinds = array('source', 'page', 'doc', 'ticket');
                $fm = PersistentObjectManager::getData('FORM_MANAGER');
                $res = isset($fm) ? $fm->getResults('AnnotateForm') : null;
                $kind = isset($res['targetKind']) ? trim((string) $res['targetKind']) : '';
                $id   = isset($res['targetId'])   ? trim((string) $res['targetId'])   : '';
                $tag  = isset($res['tag'])        ? trim((string) $res['tag'])        : '';
                $note = isset($res['note'])       ? trim((string) $res['note'])       : '';

                if ($kind === '' && $id === '' && $tag === '') {
                    return "<p>No pending annotation. <a href='?page=forms'>Add one &rarr;</a></p>";
                }
                if (!in_array($kind, $kinds, true)) {
                    return "<p style='color:#b00020'>Rejected: <code>" . self::esc($kind) . "</code> is not a valid target kind.</p>" . self::back();
                }
                if ($id === '' || $tag === '') {
                    return "<p style='color:#b00020'>Rejected: a target id and a tag are both required.</p>" . self::back();
                }
                $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $db->exec("CREATE TABLE IF NOT EXISTS annotations (id INTEGER PRIMARY KEY AUTOINCREMENT, tag TEXT, target TEXT, note TEXT, ts REAL, meta TEXT)");

                $target = $kind . ':' . $id;
                $ins = $db->prepare("INSERT INTO annotations (tag,target,note,ts,meta) VALUES (?,?,?,?,?)");
                $ins->execute(array($tag, $target, $note, microtime(true), json_encode(array('source' => 'annotate-form'))));
                $aid = (int) $db->lastInsertId();

                if (isset($fm)) { $f = $fm->getCachedForm('AnnotateForm'); if (isset($f)) { $f->setResults(array()); $f->reset(); } }

                return "<div style='border:1px solid #1a7a3a;border-left:5px solid #1a7a3a;border-radius:6px;padding:.7rem .9rem;background:#f3fbf5'>"
                     . "<p style='margin:.2rem 0'><strong>Annotation #" . self::esc($aid) . " added.</strong></p>"
                     . "<p style='margin:.2rem 0;font-size:.9rem'><code>" . self::esc($tag) . "</code> &rarr; <code>" . self::esc($target) . "</code></p>"
                     . ($note !== '' ? "<p style='margin:.2rem 0;font-size:.9rem'>" . self::esc($note) . "</p>" : "")
                     . "</div>\n<p><a href='?page=annotations'>&rarr; see it in Annotations</a></p>";
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>AnnotateHandler error: " . self::esc(get_class($e) . ': ' . $e->getMessage()) . "</div>";
            }
        }
    }
}
?>
