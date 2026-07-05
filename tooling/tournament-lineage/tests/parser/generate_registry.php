<?php
/* Builds tag-registry.json: identifier => {category,file,class} for every
 * invocator under invocators/tags/. Ground-truth capture from the filesystem. */
$ENTRY = dirname(__DIR__, 2);
$root  = "$ENTRY/invocators/tags";
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$map = [];
foreach ($rii as $f) {
    if ($f->getExtension() !== 'php') continue;
    $id  = $f->getBasename('.php');
    $rel = 'invocators/tags/' . substr($f->getPathname(), strlen($root) + 1);
    $cat = basename(dirname($f->getPathname()));
    $map[$id] = ['category' => $cat, 'file' => $rel, 'class' => $id];
}
ksort($map);
echo json_encode($map, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
