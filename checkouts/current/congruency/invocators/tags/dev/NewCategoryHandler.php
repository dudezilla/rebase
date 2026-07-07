<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * NewCategoryHandler — create a Category (the handler for NewCategoryForm), on ?page=categoryDone.
 *
 * Modeled on TicketLogger/TagPageHandler: reads the completed form via FORM_MANAGER, validates the name
 * (non-empty, not a duplicate), inserts into Categories with a MECHANICAL key (max+1), and resets the form
 * so a refresh can't double-file. Grows the tag vocabulary the page-category annotations draw on.
 */
if (!class_exists("NewCategoryHandler")) {
    class NewCategoryHandler implements Tag_Interface {

        private $arguments;
        public function __construct($arguments) { $this->arguments = $arguments; }
        private static function esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
        private static function back() { return "<p><a href='?page=forms'>&larr; back to the admin forms</a></p>"; }

        public function get_document() {
            try {
                $fm = PersistentObjectManager::getData('FORM_MANAGER');
                $res = isset($fm) ? $fm->getResults('NewCategoryForm') : null;
                $name = isset($res['name']) ? trim((string) $res['name']) : '';
                $desc = isset($res['description']) ? trim((string) $res['description']) : '';

                if ($name === '') {
                    return "<p>No pending category. <a href='?page=forms'>Create one &rarr;</a></p>";
                }
                $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $chk = $db->prepare("SELECT 1 FROM Categories WHERE name=?");
                $chk->execute(array($name));
                if ($chk->fetch()) {
                    return "<p style='color:#b00020'>Category <code>" . self::esc($name) . "</code> already exists.</p>" . self::back();
                }
                $key = (int) $db->query("SELECT COALESCE(MAX(`key`),0)+1 FROM Categories")->fetchColumn();
                $ins = $db->prepare("INSERT INTO Categories (`key`, name, description) VALUES (?,?,?)");
                $ins->execute(array($key, $name, $desc));

                if (isset($fm)) { $f = $fm->getCachedForm('NewCategoryForm'); if (isset($f)) { $f->setResults(array()); $f->reset(); } }

                return "<div style='border:1px solid #1a7a3a;border-left:5px solid #1a7a3a;border-radius:6px;padding:.7rem .9rem;background:#f3fbf5'>"
                     . "<p style='margin:.2rem 0'><strong>Category <code>" . self::esc($name) . "</code> created.</strong></p>"
                     . ($desc !== '' ? "<p style='margin:.2rem 0;font-size:.9rem'>" . self::esc($desc) . "</p>" : "")
                     . "</div>\n<p><a href='?page=pages'>&rarr; tag a page with it</a></p>";
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>NewCategoryHandler error: " . self::esc(get_class($e) . ': ' . $e->getMessage()) . "</div>";
            }
        }
    }
}
?>
