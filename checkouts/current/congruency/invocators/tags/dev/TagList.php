<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * TagList — the ?page=tags gallery. Lists every tag (invocator) under TAGS_DIR
 * as a hyperlink; clicking a tag (?page=tags&tag=NAME) renders that tag's own
 * output below the list. Only files that implement Tag_Interface are listed,
 * and only a name already in that scanned set is ever instantiated (so the
 * ?tag= param can't load an arbitrary class).
 */
if (!class_exists("TagList")) {
    class TagList implements Tag_Interface {

        private $arguments;
        private static $rendering = false;   // re-entrancy guard: selecting TagList would render itself forever
        public function __construct($arguments) { $this->arguments = $arguments; }
        private static function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

        private static function scan() {
            $names = array();
            if (!defined('TAGS_DIR') || !is_dir(TAGS_DIR)) { return $names; }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(TAGS_DIR, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                    $src = @file_get_contents($f->getPathname());
                    if ($src !== false && strpos($src, 'Tag_Interface') !== false) {
                        $names[substr($f->getFilename(), 0, -4)] = 1;   // basename minus .php
                    }
                }
            }
            $names = array_keys($names);
            sort($names);
            return $names;
        }

        public function get_document() {
            if (self::$rendering) {
                return "<em>(the tag gallery is rendered by TagList; it is not re-rendered inside itself)</em>";
            }
            self::$rendering = true;
            try {
                $names = self::scan();
                $sel = isset($_GET['tag']) ? (string)$_GET['tag'] : '';

                $out  = "<style>.taglist{display:flex;flex-wrap:wrap;gap:.4rem;list-style:none;padding:0}"
                      . ".taglist a{display:inline-block;border:1px solid #d8d2c4;border-radius:4px;padding:.15rem .5rem;background:#fffdf8;font:.85rem/1.4 monospace}"
                      . ".taglist a.sel{background:#8a5a1a;color:#fff;border-color:#8a5a1a}"
                      . ".tagout{margin-top:1.2rem;border:1px solid #d8d2c4;border-left:5px solid #8a5a1a;border-radius:6px;padding:.8rem 1rem;background:#fffdf8}"
                      . ".tagout h3{margin:0 0 .5rem;font-weight:normal}</style>\n";
                $out .= "<p style='font-size:.85rem;color:#555'><strong>" . count($names) . "</strong> tags &mdash; click one to render it.</p>\n";
                $out .= "<ul class='taglist'>\n";
                foreach ($names as $n) {
                    $cls = ($n === $sel) ? " class='sel'" : "";
                    $out .= "  <li><a$cls href='?page=tags&tag=" . self::esc($n) . "'>" . self::esc($n) . "</a></li>\n";
                }
                $out .= "</ul>\n";

                if ($sel !== '' && in_array($sel, $names, true)) {
                    $out .= "<div class='tagout'><h3>&lt;&lt;&lt;" . self::esc($sel) . "&gt;&gt;&gt;</h3>\n";
                    $loader = PersistentObjectManager::getData('TAG_LOADER');
                    if (isset($loader)) { $loader->loadClassByName($sel); }
                    if (class_exists($sel)) {
                        try {
                            $obj = new $sel(null);
                            $out .= (string)$obj->get_document();
                        } catch (\Throwable $e) {
                            $out .= "<p style='color:#b00020'>This tag needs context to render standalone: "
                                  . self::esc(get_class($e) . ': ' . $e->getMessage()) . "</p>";
                        }
                    } else {
                        $out .= "<p style='color:#b00020'>Tag class not loadable.</p>";
                    }
                    $out .= "</div>\n";
                } elseif ($sel !== '') {
                    $out .= "<p style='color:#b00020'>Unknown tag: " . self::esc($sel) . "</p>";
                }
                return $out;
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>TagList error: " . self::esc(get_class($e) . ': ' . $e->getMessage()) . "</div>";
            } finally {
                self::$rendering = false;
            }
        }
    }
}
?>
