<?php
/*
 * harness.php — runs every reproduction in bugs.json in its own PHP process and
 * reports which bugs still reproduce. A bug counts as REPRODUCED when its
 * "success" signature appears in the child's combined stdout+stderr.
 *
 * Usage:  php harness.php [BUG-ID ...]     (no args = run all)
 * Exit code = number of bugs that did NOT reproduce (0 = all reproduced).
 */

$root  = __DIR__;
$bugs  = json_decode(file_get_contents("$root/bugs.json"), true);
if (!is_array($bugs)) { fwrite(STDERR, "Cannot read bugs.json\n"); exit(99); }

$only  = array_slice($argv, 1);
$php   = PHP_BINARY;                 // reproduce with the same interpreter running us
$pass  = 0; $fail = 0;
$C = fn($c, $s) => posix_isatty(STDOUT) ? "\033[{$c}m$s\033[0m" : $s;

echo "\n  congruency bug catalog — reproducing against " . PHP_VERSION . "\n";
echo "  " . str_repeat("─", 68) . "\n";

foreach ($bugs as $b) {
    if ($only && !in_array($b['id'], $only, true)) continue;

    $env = 'CONGRUENCEY_PATH=' . escapeshellarg(getenv('CONGRUENCEY_PATH') ?: "$root/vendor/congruency");
    foreach (($b['env'] ?? []) as $k => $v) $env .= ' ' . $k . '=' . escapeshellarg($v);
    $flags = implode(' ', array_map('escapeshellarg', $b['php_flags'] ?? []));
    $cmd   = "$env " . escapeshellarg($php) . " $flags " . escapeshellarg("$root/{$b['repro']}") . " 2>&1";

    exec($cmd, $out, $code);
    $output = implode("\n", $out);
    $out = [];                                   // reset for next iteration
    $ok  = strpos($output, $b['success']) !== false;
    $ok ? $pass++ : $fail++;

    $tag = $ok ? $C('42;30', ' REPRODUCED ') : $C('41;30', '    FAILED    ');
    printf("  %s  %s  %s\n", $tag, str_pad(strtoupper($b['severity']), 8), $b['id']);
    echo   "              " . $b['title'] . "\n";
    echo   "              " . $C('90', $b['file']) . "\n";
    // Show the single most relevant line of evidence (the observed result,
    // not the "expected:" line that also contains the signature).
    foreach (explode("\n", $output) as $line) {
        if (stripos(ltrim($line), 'expected:') === 0) continue;
        if (stripos($line, 'observed:') !== false
            || stripos($line, 'CONFIRMED') !== false
            || stripos($line, $b['success']) !== false) {
            echo "              " . $C('90', '> ' . trim($line)) . "\n";
            break;
        }
    }
    echo "\n";
}

echo "  " . str_repeat("─", 68) . "\n";
printf("  %d reproduced, %d failed, %d total\n\n", $pass, $fail, $pass + $fail);
exit($fail);
