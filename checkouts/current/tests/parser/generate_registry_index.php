<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Derives a category reverse-index from tag-registry.json: category => sorted list
 * of tag classes. Mapped data (goal #2); each category grouping is a data element. */
$reg = json_decode(file_get_contents(__DIR__ . '/tag-registry.json'), true);
$by = [];
foreach ($reg as $id => $d) $by[$d['category']][] = $id;
foreach ($by as &$list) sort($list);
ksort($by);
echo json_encode($by, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
