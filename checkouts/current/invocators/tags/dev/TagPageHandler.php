<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * TagPageHandler — file a page->category tag (the handler for TagPageForm).
 *
 * Modeled on TicketLogger: on ?page=tagDone it reads the completed TagPageForm
 * results (pageId, category) via FORM_MANAGER, validates both against the DB,
 * and inserts a row into Page_Categories. Idempotent (INSERT OR IGNORE) and
 * self-consuming (resets the form) so a refresh can't double-file.
 */
if (!class_exists("TagPageHandler")) {
    class TagPageHandler implements Tag_Interface {

        private $arguments;
        public function __construct($arguments) { $this->arguments = $arguments; }
        private static function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }
        private static function back() { return "<p><a href='?page=pages'>&larr; back to tag a page</a></p>"; }

        public function get_document() {
            try {
                $fm = PersistentObjectManager::getData('FORM_MANAGER');
                $res = isset($fm) ? $fm->getResults('TagPageForm') : null;
                $pageId   = isset($res['pageId'])   ? trim((string)$res['pageId'])   : '';
                $category = isset($res['category']) ? trim((string)$res['category']) : '';

                if ($pageId === '' && $category === '') {
                    return "<p>No pending page tag. <a href='?page=pages'>Tag a page &rarr;</a></p>";
                }
                $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $st = $db->prepare("SELECT 1 FROM Documents WHERE DocumentID=?");
                $st->execute(array($pageId));
                if (!$st->fetch()) { return "<p style='color:#b00020'>No such page: <code>" . self::esc($pageId) . "</code></p>" . self::back(); }

                $st = $db->prepare("SELECT `key` FROM Categories WHERE name=?");
                $st->execute(array($category));
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) { return "<p style='color:#b00020'>No such category: <code>" . self::esc($category) . "</code></p>" . self::back(); }
                $key = (int)$row['key'];

                $ins = $db->prepare("INSERT OR IGNORE INTO Page_Categories (DocumentID, category_key) VALUES (?,?)");
                $ins->execute(array($pageId, $key));
                $added = $ins->rowCount() > 0;

                if (isset($fm)) { $f = $fm->getCachedForm('TagPageForm'); if (isset($f)) { $f->setResults(array()); $f->reset(); } }

                $msg = $added
                    ? "Tagged page <code>" . self::esc($pageId) . "</code> as <code>" . self::esc($category) . "</code>."
                    : "Page <code>" . self::esc($pageId) . "</code> was already tagged <code>" . self::esc($category) . "</code>.";
                return "<div style='border:1px solid #1a7a3a;border-left:5px solid #1a7a3a;border-radius:6px;padding:.7rem .9rem;background:#f3fbf5'>"
                     . "<p style='margin:.2rem 0'><strong>" . $msg . "</strong></p></div>\n"
                     . "<p><a href='?page=pages&category=" . self::esc($category) . "'>&rarr; see pages in " . self::esc($category) . "</a></p>";
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>TagPageHandler error: " . self::esc(get_class($e) . ': ' . $e->getMessage()) . "</div>";
            }
        }
    }
}
?>
