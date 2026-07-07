<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * TicketList — a congruency component (tag/invocator).
 *
 * Implements Tag_Interface, so the tag engine instantiates it whenever a
 * document's content contains <<<TicketList>>> and splices in get_document().
 * Renders the project ticket table from the unified congruency DB
 * (CONGRUENCY_SQLITE) — the same file the CMS, telemetry and gate all share.
 *
 * NB: ticket titles may themselves contain <<<Tag>>> syntax (e.g. this very
 * ticket, "render ... a <<<TicketList>>> ..."), so EVERY value is passed
 * through htmlspecialchars() — otherwise execute_all_tags would re-parse the
 * output and recurse. (This models BugReport.php, but reads live rows.)
 */
if (!class_exists("TicketList")) {
    class TicketList implements Tag_Interface {

        private $arguments;

        public function __construct($arguments) {
            $this->arguments = $arguments;
        }

        private function rows() {
            if (!defined('CONGRUENCY_SQLITE')) {
                return array(null, "CONGRUENCY_SQLITE is not defined");
            }
            try {
                $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // OPEN first, then newest id first
                $sql = "SELECT id, component, severity, status, title, meta "
                     . "FROM tickets ORDER BY (status='OPEN') DESC, id DESC";
                return array($db->query($sql)->fetchAll(PDO::FETCH_ASSOC), null);
            } catch (Exception $e) {
                return array(null, $e->getMessage());
            }
        }

        private static function sev_colour($sev) {
            $map = array('critical' => '#b00020', 'high' => '#b5651d',
                         'medium' => '#8a7a1a', 'low' => '#5a6a5a');
            $sev = strtolower((string)$sev);
            return isset($map[$sev]) ? $map[$sev] : '#666';
        }

        private static function status_colour($st) {
            return strtoupper((string)$st) === 'OPEN' ? '#1a7a3a' : '#888';
        }

        private static function esc($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES);
        }

        public function get_document() {
            list($rows, $err) = $this->rows();
            if ($err !== null) {
                return "<p style='color:#b00020'>TicketList: could not read tickets ("
                     . self::esc($err) . ")</p>";
            }
            $open = 0;
            foreach ($rows as $r) {
                if (strtoupper($r['status']) === 'OPEN') { $open++; }
            }
            $total = count($rows);

            $out  = "<style>";
            $out .= ".tks{display:grid;gap:.7rem}";
            $out .= ".tk{border:1px solid #d8d2c4;border-left:5px solid #999;border-radius:6px;padding:.6rem .9rem;background:#fffdf8}";
            $out .= ".tk h3{margin:0 0 .2rem;font-size:1rem;font-weight:normal}";
            $out .= ".tk .id{font:600 .72rem/1 monospace;letter-spacing:.05em;color:#555}";
            $out .= ".tk .st{float:right;font:600 .62rem/1.6 sans-serif;text-transform:uppercase;color:#fff;padding:.1rem .45rem;border-radius:3px}";
            $out .= ".tk .cm{font:.72rem/1 monospace;color:#8a5a1a}";
            $out .= ".tks .sev{font:600 .6rem/1.6 sans-serif;text-transform:uppercase;color:#fff;padding:.05rem .4rem;border-radius:3px;margin-left:.4rem}";
            $out .= "</style>\n";
            $out .= "<p style='font-size:.85rem;color:#555'><strong>" . $total
                  . "</strong> tickets &middot; <strong>" . $open . "</strong> open &middot; <strong>"
                  . ($total - $open) . "</strong> closed</p>\n";
            $out .= "<div class='tks'>\n";
            foreach ($rows as $r) {
                $sc = self::sev_colour($r['severity']);
                $stc = self::status_colour($r['status']);
                $out .= "  <div class='tk' style='border-left-color:$stc'>";
                $out .= "<span class='st' style='background:$stc'>" . self::esc($r['status']) . "</span>";
                $out .= "<span class='id'>#" . self::esc($r['id']) . "</span>";
                $out .= "<span class='sev' style='background:$sc'>" . self::esc($r['severity']) . "</span>";
                $out .= "<h3>" . self::esc($r['title']) . "</h3>";
                $out .= "<span class='cm'>" . self::esc($r['component']) . "</span>";
                $out .= "</div>\n";
            }
            $out .= "</div>\n";
            return $out;
        }
    }
}
?>
