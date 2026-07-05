<?php
/* Derives a category reverse-index from tag-registry.json: category => sorted list
 * of tag classes. Mapped data (goal #2); each category grouping is a data element. */
$reg = json_decode(file_get_contents(__DIR__ . '/tag-registry.json'), true);
$by = [];
foreach ($reg as $id => $d) $by[$d['category']][] = $id;
foreach ($by as &$list) sort($list);
ksort($by);
echo json_encode($by, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
