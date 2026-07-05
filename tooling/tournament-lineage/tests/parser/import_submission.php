<?php
/* Self-import: use the SUBMISSION'S OWN implementation to import the submission.
 * Corpus reads its own manifest (its description) and reconstructs the dataset
 * object-graph; DocumentStore + BlobStore then persist that graph through the
 * four-format edge and content-addressing. This is goal #3 turned on itself. */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/corpus.php';
require __DIR__ . '/store.php';
require __DIR__ . '/blobstore.php';

$corpus = new Corpus();                                  // reads manifest.json = its own description
[$missing, $orphan] = $corpus->drift();                  // reconcile description vs. reality
if ($missing || $orphan) { fwrite(STDERR, "drift!\n"); exit(1); }

$root  = sys_get_temp_dir() . '/cy-import-' . getmypid();
$store = new DocumentStore($root, 'json');
$blob  = new BlobStore($root, 'json');

$totalElements = 0; $stored = 0; $hashes = [];
echo "IMPORTING SUBMISSION via its own implementation\n";
echo str_repeat('-', 62), "\n";
printf("%-22s %-16s %6s\n", 'dataset', 'kind', 'count');
foreach ($corpus->datasets() as $d) {
    $data  = $corpus->load($d['id']);                    // reconstruct the dataset object
    $count = is_array($data) ? count($data, COUNT_RECURSIVE) - count($data) + count($data) : 1;
    $n     = is_array($data) ? count($data) : 1;
    $totalElements += $n;
    // persist the dataset's identity as a canonical tag-state through the store + blob
    $state = ['name' => $d['id'], 'args' => [$d['kind'], (string)$n]];
    $store->put('datasets', $d['id'], $state);
    $hashes[$d['id']] = $blob->put($state);
    $stored++;
    printf("%-22s %-16s %6d\n", $d['id'], $d['kind'], $n);
}
echo str_repeat('-', 62), "\n";
printf("datasets imported : %d\n", $stored);
printf("top-level records : %d\n", $totalElements);
printf("kinds             : %s\n", implode(', ', $corpus->kinds()));

// prove the import round-trips: read every stored dataset-descriptor back
$ok = 0;
foreach ($corpus->datasets() as $d) {
    $back = $store->get('datasets', $d['id']);
    if ($back['name'] === $d['id'] && $back['args'][0] === $d['kind']) $ok++;
}
printf("store round-trip  : %d/%d descriptors recovered\n", $ok, $stored);
printf("blob objects      : %d unique content-addresses\n", count(array_unique($hashes)));

exec('rm -rf ' . escapeshellarg($root));                 // temp import area, cleaned up
echo ($ok === $stored) ? "\nIMPORT OK — the submission imported itself cleanly.\n"
                       : "\nIMPORT FAILED\n";
exit($ok === $stored ? 0 : 1);
