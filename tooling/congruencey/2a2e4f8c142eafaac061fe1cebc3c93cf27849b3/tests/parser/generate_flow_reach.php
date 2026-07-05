<?php
/* BFS the form-flow transition table from the start state to compute the shortest
 * action-path to every reachable state. Ground-truth reachability map (goal #5). */
require __DIR__ . '/flow.php';
$flow = new FormFlow();
$f = json_decode(file_get_contents(__DIR__ . '/form-flow.json'), true);
$trans = $f['transitions'];

$start = $flow->start();
$paths = [$start => []];                       // state => shortest action list
$queue = [$start];
while ($queue) {
    $s = array_shift($queue);
    foreach (($trans[$s] ?? []) as $action => $next) {
        if (!isset($paths[$next])) {
            $paths[$next] = array_merge($paths[$s], [$action]);
            $queue[] = $next;
        }
    }
}
ksort($paths);
echo json_encode($paths, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
