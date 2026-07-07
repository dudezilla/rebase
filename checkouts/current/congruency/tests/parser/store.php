<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* DocumentStore — persistence with git as the primary store (goal #6).
 *
 * Canonical state {name,args} is written to plain files under a store root; that
 * root is a git repo, so every put is a versioned, content-addressable object and
 * history IS the database. SQL is reserved for awkward mutable items (forms).
 * Serialization reuses the format edge (TagStateRenderer), so documents can be
 * persisted in ANY of the four formats — the store never bakes one in. */
require_once __DIR__ . '/render.php';
require_once __DIR__ . '/collection.php';
final class DocumentStore {
    private const EXT = ['json'=>'json','yaml'=>'yaml','xml'=>'xml','html'=>'html'];
    public function __construct(private string $root, private string $format = 'json') {
        if (!isset(self::EXT[$this->format])) throw new InvalidArgumentException("bad format: $format");
    }
    public function path(string $collection, string $id): string {
        return "{$this->root}/{$collection}/{$id}." . self::EXT[$this->format];
    }
    public function put(string $collection, string $id, array $state): string {
        $p = $this->path($collection, $id);
        @mkdir(dirname($p), 0777, true);
        file_put_contents($p, TagStateRenderer::render($this->format, $state));
        return $p;                                   // in prod: `git add $p && git commit`
    }
    public function has(string $collection, string $id): bool {
        return is_file($this->path($collection, $id));
    }
    public function get(string $collection, string $id): array {
        $p = $this->path($collection, $id);
        if (!is_file($p)) throw new OutOfBoundsException("no document: $collection/$id");
        return TagStateRenderer::parse($this->format, file_get_contents($p));
    }
    public function list(string $collection): array {
        $dir = "{$this->root}/{$collection}";
        if (!is_dir($dir)) return [];
        $ids = [];
        foreach (glob("$dir/*." . self::EXT[$this->format]) as $f)
            $ids[] = basename($f, '.' . self::EXT[$this->format]);
        sort($ids);
        return $ids;
    }

    /* --- collection persistence: a whole state-list stored as one document --- */
    private function collPath(string $collection, string $id): string {
        return "{$this->root}/{$collection}/{$id}.coll." . self::EXT[$this->format];
    }
    public function putCollection(string $collection, string $id, array $states): string {
        $p = $this->collPath($collection, $id);
        @mkdir(dirname($p), 0777, true);
        file_put_contents($p, CollectionRenderer::render($this->format, $states));
        return $p;
    }
    public function getCollection(string $collection, string $id): array {
        $p = $this->collPath($collection, $id);
        if (!is_file($p)) throw new OutOfBoundsException("no collection: $collection/$id");
        return CollectionRenderer::parse($this->format, file_get_contents($p));
    }
}
