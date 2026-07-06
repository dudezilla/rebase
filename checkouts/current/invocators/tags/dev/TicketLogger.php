<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * TicketLogger — finalize a ticket submission (the handler half of #38).
 *
 * Modeled on OrdererTag: invoked as <<<TicketLogger>>> on ?page=ticketDone,
 * it reads the POSTed ticket, validates `type` against the in_array allowlist
 * sourced from the forms table (TicketForm::spec), and inserts a row into the
 * unified `tickets` table (jazz schema) with a MECHANICAL id (SQLite
 * AUTOINCREMENT) — callers never supply the id, same discipline as jazz
 * open_ticket() and the gate stamping time. Renders the confirmation.
 * Wrapped in try/catch: the tag engine has no error boundary (BUG-06).
 */
if (!class_exists("TicketLogger")) {
    class TicketLogger implements Tag_Interface {

        private $arguments;
        public function __construct($arguments) { $this->arguments = $arguments; }

        private static function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

        /* The type allowlist, read straight from the forms table (no cross-tag class ref — the tag
         * loader and the app autoloader are different ClassLoaders; a tag cannot autoload another tag). */
        private static function allowed_types() {
            $types = array();
            if (!defined('CONGRUENCY_SQLITE')) { return $types; }
            $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $row = $db->query("SELECT elementString FROM forms WHERE formName='TicketForm' AND name='type'")->fetch(PDO::FETCH_ASSOC);
            if ($row) { preg_match_all('/<<([^>]+)>>/', (string)$row['elementString'], $m); $types = $m[1]; }
            return $types;
        }

        /* TicketDAO-style insert: the only writer of web-submitted tickets. */
        private static function insert_ticket($type, $description, $severity = 'medium') {
            $db = new PDO('sqlite:' . CONGRUENCY_SQLITE);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $now = microtime(true);
            $meta = json_encode(array('source' => 'web-form', 'type' => $type));
            $st = $db->prepare("INSERT INTO tickets (ts,updated,component,title,severity,status,body,meta)
                                VALUES (:ts,:up,:cm,:ti,:sev,'OPEN',:bo,:me)");
            $st->execute(array(':ts' => $now, ':up' => $now, ':cm' => $type, ':sev' => $severity,
                               ':ti' => $description, ':bo' => $description, ':me' => $meta));
            return (int)$db->lastInsertId();
        }

        public function get_document() {
            try {
                // Native forms pipeline: the completed form's results are carried here (the FCE
                // action=?page=ticketDone) via the POM-in-session, read through FORM_MANAGER.
                $fm = PersistentObjectManager::getData('FORM_MANAGER');
                $res = isset($fm) ? $fm->getResults('TicketForm') : null;
                $type = isset($res['type']) ? trim((string)$res['type']) : '';
                $description = isset($res['description']) ? trim((string)$res['description']) : '';

                if ($type === '' && $description === '') {
                    return "<p>No pending ticket submission. <a href='?page=tickets'>File one &rarr;</a></p>";
                }

                $allow = self::allowed_types();               // allowlist FROM the forms table
                if (!in_array($type, $allow, true)) {
                    return "<p style='color:#b00020'>Rejected: <code>" . self::esc($type)
                         . "</code> is not an allowed ticket type.</p>"
                         . "<p><a href='?page=tickets'>&larr; back to the form</a></p>";
                }
                if ($description === '') {
                    return "<p style='color:#b00020'>Rejected: a description is required.</p>"
                         . "<p><a href='?page=tickets'>&larr; back to the form</a></p>";
                }

                $urgent = isset($res['urgent']) && $res['urgent'] !== '' && $res['urgent'] !== null;
                $id = self::insert_ticket($type, $description, $urgent ? 'high' : 'medium');
                // consume the submission so a refresh/return can't file it twice
                if (isset($fm)) {
                    $form = $fm->getCachedForm('TicketForm');
                    if (isset($form)) { $form->setResults(array()); $form->reset(); }
                }
                return "<div style='border:1px solid #1a7a3a;border-left:5px solid #1a7a3a;border-radius:6px;"
                     . "padding:.7rem .9rem;background:#f3fbf5'>"
                     . "<p style='margin:.2rem 0'><strong>Ticket #" . self::esc($id) . " logged.</strong></p>"
                     . "<p style='margin:.2rem 0;font-size:.9rem'>type <code>" . self::esc($type)
                     . "</code> &middot; status <code>OPEN</code></p>"
                     . "<p style='margin:.4rem 0 .1rem;font-size:.9rem'>" . self::esc($description) . "</p>"
                     . "</div>\n<p><a href='?page=tickets'>&larr; see it in the ticket list</a></p>";
            } catch (\Throwable $e) {
                return "<div class='cy-form-error'>TicketLogger error: "
                     . self::esc(get_class($e) . ': ' . $e->getMessage()) . "</div>";
            }
        }
    }
}
?>
