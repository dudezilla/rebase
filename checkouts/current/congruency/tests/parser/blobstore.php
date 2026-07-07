<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* BlobStore — content-addressed persistence, git-object style (goal #6). A state's
 * address is the sha1 of its canonical serialization, so identical states dedupe to
 * one immutable object and the address never lies about content. */
require_once __DIR__ . '/render.php';
final class BlobStore {
    public function __construct(private string $root, private string $format = 'json') {}
    public function hash(array $state): string {
        return sha1(TagStateRenderer::render($this->format, $state));
    }
    private function path(string $hash): string {
        return "{$this->root}/objects/" . substr($hash, 0, 2) . '/' . substr($hash, 2);
    }
    public function put(array $state): string {
        $h = $this->hash($state);
        $p = $this->path($h);
        @mkdir(dirname($p), 0777, true);
        file_put_contents($p, TagStateRenderer::render($this->format, $state));  // idempotent write
        return $h;
    }
    public function has(string $hash): bool { return is_file($this->path($hash)); }
    public function get(string $hash): array {
        $p = $this->path($hash);
        if (!is_file($p)) throw new OutOfBoundsException("no object: $hash");
        return TagStateRenderer::parse($this->format, file_get_contents($p));
    }
    public function countObjects(): int {
        return count(glob("{$this->root}/objects/*/*") ?: []);
    }
}
