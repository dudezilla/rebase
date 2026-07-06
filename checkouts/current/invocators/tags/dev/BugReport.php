<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * BugReport — a congruency component (tag/invocator).
 *
 * Implements Tag_Interface, so the tag engine instantiates it whenever a
 * document's content contains <<<BugReport>>> and splices in get_document().
 * Renders the catalog of bugs found in congruency itself. Meta by design:
 * the CMS is publishing a page about its own defects, through its own pipeline.
 *
 * NOTE: any <<<Name>>> we emit would be re-parsed by execute_all_tags, so every
 * example of tag syntax below is HTML-escaped (&lt;&lt;&lt;) on purpose.
 */
if (!class_exists("BugReport")) {
    class BugReport implements Tag_Interface {

        private $arguments;

        public function __construct($arguments) {
            $this->arguments = $arguments;
        }

        private function catalog() {
            $gh = 'https://github.com/dudezilla/congruency/blob/main/';
            return array(
                array('BUG-01', 'critical', 'SQL injection in the product catalog',
                    'select_products_by_category() validates the key into $itemKey, then guards on isset($key) and interpolates the RAW $key into SQL. The validated value is dead code one variable name away.',
                    'lib/Modules/StoreModule/Catalog/DAO/CatalogDAO.php#L58',
                    'FIXED &mdash; CatalogDAO now interpolates the validated $itemKey and guards isset($itemKey); malicious keys sanitise to NULL.'),
                array('BUG-02', 'high', 'ProductDAO never opens a connection',
                    'The constructor sets $this->table but omits the CreateConnection()->open() every sibling DAO has. First query dereferences null.',
                    'lib/Modules/StoreModule/Catalog/DAO/ProductDAO.php#L25',
                    'FIXED &mdash; the constructor now opens the STORE connection like sibling DAOs.'),
                array('BUG-03', 'high', 'OrderDAO::updateRow calls a method that does not exist',
                    'Calls $this->insert() (no such method) and passes a bare key to delete() where a WHERE-clause is expected.',
                    'lib/Modules/StoreModule/Order/DAO/OrderDAO.php#L65',
                    'FIXED &mdash; updateRow now uses deleteRow() (a WHERE clause) and insertRow() (the real insert).'),
                array('BUG-04', 'medium', 'Empty result set returns null, not []',
                    'returnAllBeans() inits to NULL and only fills in-loop; a zero-row query returns null and callers do current(null) -> TypeError.',
                    'lib/DatabaseDrivers/MySQL/AbstractDAO.php#L86',
                    'FIXED &mdash; returnAllBeans() now inits array(); a zero-row query returns [].'),
                array('BUG-05', 'medium', 'A missing page crashes the front controller',
                    'Controller::display passes a possibly-null document to setData(), which does get_class(null). Silent false in PHP 5; fatal TypeError in PHP 8.',
                    'lib/PersistanceObjectManager/PersistentObjectManager.php#L75'),
                array('BUG-06', 'medium', 'Unbounded tag recursion (stored-content DoS)',
                    'execute_all_tags recurses into a tag&rsquo;s rendered output with no depth/visited guard. A document whose Title is &lt;&lt;&lt;TitleTag&gt;&gt;&gt; loops forever.',
                    'lib/TagLoader/Tag/Tag_Wrapper.php#L81',
                    'FIXED &mdash; execute_all_tags now caps recursion depth at 64.'),
                array('BUG-07', 'high', 'Config constants referenced but never defined',
                    'The store/order/auth modules use MYSQL_STORE_DATABASE, ETC, &hellip; that Constants.php never defines. Undefined constants are fatal on PHP 8 &mdash; why Install.txt says &ldquo;does not execute.&rdquo;',
                    'www/Constants.php'),
                array('BUG-08', 'low', 'Command queue runs in reverse',
                    'execute() drains the queue with array_pop(), so commands fire last-in-first-out.',
                    'lib/CommandQueues/InterfaceObject/CommandInterfaceObject.php#L38'),
            );
        }

        private static function colour($sev) {
            $map = array('critical' => '#b00020', 'high' => '#b5651d', 'medium' => '#8a7a1a', 'low' => '#5a6a5a');
            return isset($map[$sev]) ? $map[$sev] : '#666';
        }

        public function get_document() {
            $gh = 'https://github.com/dudezilla/congruency/blob/main/';
            $out  = "<style>";
            $out .= ".bugs{display:grid;gap:.8rem}";
            $out .= ".bug{border:1px solid #d8d2c4;border-left:5px solid #999;border-radius:6px;padding:.7rem .9rem;background:#fffdf8}";
            $out .= ".bug h3{margin:0 0 .2rem;font-size:1rem;font-weight:normal}";
            $out .= ".bug .id{font:600 .72rem/1 monospace;letter-spacing:.05em}";
            $out .= ".bug .sev{float:right;font:600 .62rem/1.6 sans-serif;text-transform:uppercase;color:#fff;padding:.1rem .45rem;border-radius:3px}";
            $out .= ".bug p{margin:.35rem 0 .3rem;font-size:.85rem;color:#444;line-height:1.45}";
            $out .= ".bug a{font:.72rem/1 monospace;color:#8a5a1a}";
            $out .= "</style>\n<div class='bugs'>\n";
            foreach ($this->catalog() as $b) {
                list($id, $sev, $title, $desc, $loc, $fixed) = array_pad($b, 6, null);
                $c = $fixed ? '#1a7a3a' : self::colour($sev);
                $out .= "  <div class='bug' style='border-left-color:$c" . ($fixed ? ";opacity:.72" : "") . "'>";
                $out .= "<span class='sev' style='background:$c'>" . ($fixed ? 'resolved' : $sev) . "</span>";
                $out .= "<span class='id'>$id</span>";
                $out .= "<h3" . ($fixed ? " style='text-decoration:line-through'" : "") . ">$title</h3>";
                $out .= "<p>$desc</p>";
                if ($fixed) { $out .= "<p style='color:#1a7a3a;font-weight:600;margin:.2rem 0'>&#10003; $fixed</p>"; }
                $out .= "<a href='$gh$loc'>$loc</a>";
                $out .= "</div>\n";
            }
            $out .= "</div>\n";
            return $out;
        }
    }
}
?>
