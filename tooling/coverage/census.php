<?php
/* Census: how many branch points does the whole app actually have?
   Contextualizes what "cover every branch point" would take. */
require __DIR__ . '/coverage.php';
$root = '/home/notificationsforsteven/congruency';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$total = 0; $files = 0; $per = [];
foreach ($rii as $f) {
    if ($f->getExtension() !== 'php') continue;
    if (strpos($f->getPathname(), '/.git/') !== false) continue;
    $b = [];
    @cov_instrument(file_get_contents($f->getPathname()), $b);
    $c = count($b);
    $total += $c; $files++;
    if ($c) $per[str_replace($root . '/', '', $f->getPathname())] = $c;
}
arsort($per);
printf("Branch points across %d PHP files: %d\n", $files, $total);
echo "Most branch-dense files:\n";
$i = 0;
foreach ($per as $file => $c) { printf("  %3d  %s\n", $c, $file); if (++$i >= 8) break; }
printf("\nOne module (ValidateFields, 6 branches) is fully covered.\n"
     . "Full-app branch coverage = ~%d branch points; feasible per-module,\n"
     . "but needs xdebug (which this static build can't load) to measure at scale.\n", $total);
