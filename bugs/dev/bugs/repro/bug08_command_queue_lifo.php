<?php
/* BUG-08  Command queue executes in LIFO order
   CommandInterfaceObject::execute() drains the queue with array_pop(), so
   commands run in the REVERSE of the order they were enqueued. Harmless for a
   lone Redirect, but any two order-dependent commands run backwards.
   vendor/congruencey/lib/CommandQueues/InterfaceObject/CommandInterfaceObject.php:38 */
require __DIR__ . '/../src/bootstrap.php';

// A minimal concrete Command that records when it runs.
class ProbeCommand implements Command {
    public static $order = [];
    private $name;
    public static function commandFactory($parameters) { $c = new self(); $c->name = $parameters; return $c; }
    public function execute() { self::$order[] = $this->name; }
    public function __toString() { return "ProbeCommand({$this->name})"; }
}

function reproduce(): void {
    $queue = new CommandInterfaceObject();
    $queue->enqueueCommand(ProbeCommand::commandFactory('FIRST'));
    $queue->enqueueCommand(ProbeCommand::commandFactory('SECOND'));
    $queue->execute();

    $ran = implode(' then ', ProbeCommand::$order);
    echo "enqueued: FIRST then SECOND\n";
    echo "executed: $ran\n";
    if (ProbeCommand::$order === ['SECOND', 'FIRST']) {
        echo "LIFO CONFIRMED: commands execute in reverse enqueue order\n";
    } else {
        echo "order preserved in this build\n";
    }
}

reproduce();
