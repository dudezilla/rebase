<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * BugDemo — a congruency component that runs a bug LIVE inside the page.
 *
 * Usage in document content:  <<<BugDemo(sqli)>>>   or   <<<BugDemo(lifo)>>>
 * (tag arguments are alphanumeric only, per the FUNCTION_ARGUMENT regex, so
 * bug ids here are short slugs.)
 *
 * Only the non-fatal bugs are demonstrated live; the fatal ones (recursion,
 * null get_class, missing constants) are described by BugReport instead of
 * being triggered, since firing them would take the page down with it.
 */
if (!interface_exists("Command")) { /* loaded lazily below when needed */ }

if (!class_exists("BugDemo")) {

    class BugDemo implements Tag_Interface {

        private $which;

        public function __construct($arguments) {
            // TagArguments::pop() yields the single (slug) argument.
            $this->which = $arguments ? $arguments->pop() : '';
        }

        public function get_document() {
            try {
                switch ($this->which) {
                    case 'sqli': return $this->demo_sqli();
                    case 'lifo': return $this->demo_lifo();
                    default:     return $this->box('BugDemo', "Unknown demo '" . htmlspecialchars($this->which) . "'. Try sqli or lifo.");
                }
            } catch (\Throwable $e) {
                return $this->box('BugDemo error', htmlspecialchars(get_class($e) . ': ' . $e->getMessage()));
            }
        }

        /* ---- BUG-01 live: validated key discarded, raw input reaches SQL ---- */
        private function demo_sqli() {
            $dao    = new CatalogDAO();
            $legit  = $dao->select_products_by_category('5');          // category 5
            $inject = $dao->select_products_by_category('0 OR 1=1');   // "validated" — but ignored

            $leaked = array();
            foreach ($inject as $row) { $leaked[] = htmlspecialchars($row['name']); }

            $body  = "<code>select_products_by_category('5')</code> &rarr; <b>" . count($legit) . "</b> row(s)<br>";
            $body .= "<code>select_products_by_category('0 OR 1=1')</code> &rarr; <b>" . count($inject) . "</b> row(s)";
            if (count($inject) > count($legit)) {
                $body .= "<br><br><b style='color:#b00020'>Injected.</b> The <code>WHERE</code> clause became "
                       . "<code>category=0 OR 1=1</code>, leaking every product: " . implode(', ', $leaked) . ".";
            } else {
                $body .= "<br><br><b style='color:#1a7a3a'>Blocked &#10003;</b> The validated numeric key sanitised the input "
                       . "(<code>0 OR 1=1</code> &rarr; <code>NULL</code>), so no injectable query was built.";
            }
            return $this->box('BUG-01 &mdash; PATCHED (was live SQL injection)', $body);
        }

        /* ---- BUG-08 live: the command "queue" runs last-in-first-out ---- */
        private function demo_lifo() {
            // Ensure the Command interface + a tiny concrete command are available.
            getClassLoader()->loadClassByName('Command');
            if (!class_exists('BugDemoOrderProbe')) {
                eval('class BugDemoOrderProbe implements Command {
                    public static $ran = array(); private $name;
                    public static function commandFactory($p){ $c=new self(); $c->name=$p; return $c; }
                    public function execute(){ self::$ran[] = $this->name; }
                    public function __toString(){ return "probe(".$this->name.")"; }
                }');
            }
            BugDemoOrderProbe::$ran = array();
            $queue = new CommandInterfaceObject();
            $queue->enqueueCommand(BugDemoOrderProbe::commandFactory('FIRST'));
            $queue->enqueueCommand(BugDemoOrderProbe::commandFactory('SECOND'));
            $queue->execute();

            $body  = "enqueued: <code>FIRST</code> then <code>SECOND</code><br>";
            $body .= "executed: <code>" . implode('</code> then <code>', BugDemoOrderProbe::$ran) . "</code>";
            if (BugDemoOrderProbe::$ran === array('SECOND', 'FIRST')) {
                $body .= "<br><br><b style='color:#b5651d'>Reversed.</b> <code>execute()</code> drains with "
                       . "<code>array_pop()</code>, so the queue is really a stack.";
            }
            return $this->box('BUG-08 &mdash; live command-queue ordering', $body);
        }

        private function box($title, $body) {
            return "<div style='border:1px solid #d8d2c4;border-radius:6px;background:#f3efe4;"
                 . "padding:.7rem .9rem;margin:.6rem 0;font-size:.85rem'>"
                 . "<div style='font:600 .72rem/1 monospace;color:#7a5a2a;margin-bottom:.4rem'>&#9654; "
                 . $title . "</div>" . $body . "</div>";
        }
    }
}
?>
