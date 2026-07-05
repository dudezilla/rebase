<?php
/* BUG-06  Unbounded recursion in Tag_Wrapper::execute_all_tags (stored-content DoS)
   execute_all_tags recurses into a tag's RENDERED OUTPUT looking for more tags,
   with no visited-set / depth cap. A document whose stored Title is itself a
   <<<TitleTag>>> makes TitleTag emit a string containing its own trigger, so the
   engine recurses forever and exhausts memory (or the C stack).
   vendor/congruencey/lib/TagLoader/Tag/Tag_Wrapper.php:81

   NOTE: memory exhaustion is NOT catchable, so this script is expected to die
   with a non-zero exit. The harness runs it with a low memory_limit and treats
   "Allowed memory size ... exhausted" as a successful reproduction. */
require __DIR__ . '/../src/bootstrap.php';

function reproduce(): void {
    include BIN . 'Initialize_POM.php';   // registers TAG_LOADER so tags can load

    // A page whose stored Title is a tag invocation -> self-reproducing output.
    $pdo = new PDO('sqlite:' . CONGRUENCY_SQLITE);
    $pdo->prepare("INSERT INTO Documents VALUES ('boom',1,?, 'x',1)")
        ->execute(["<<<TitleTag>>>"]);
    unset($pdo);

    $doc = DocumentManager::get_document('boom');
    PersistentObjectManager::setData('WORKING_PAGE', $doc);   // what Controller::display does

    echo "expected: Fatal: Allowed memory size ... exhausted (unbounded recursion)\n";
    echo "rendering self-referential document...\n";
    echo $doc->__toString();                                  // never returns
    echo "\nobserved: (returned normally)\nNOT REPRODUCED\n";
}

reproduce();
