<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Captures parser behavior under BOTH tag-syntax constant variants by running
 * probe_variant.php in subprocesses (constants are define-once per process). */
$php = PHP_BINARY;
$probe = __DIR__ . '/probe_variant.php';
$run = function(string $v) use ($php, $probe): array {
    $o = []; exec(escapeshellarg($php) . ' ' . escapeshellarg($probe) . ' ' . escapeshellarg($v) . ' 2>/dev/null', $o);
    return json_decode(implode("\n", $o), true);
};
echo json_encode(['etc' => $run('etc'), 'www' => $run('www')],
                 JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
