<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* GitStore — goal #6 made concrete: a real git repo IS the primary store. Each
 * put() writes the canonical state (via the shared JSON edge) and commits it, so
 * history is the version log. Deterministic commits (fixed identity + dates).
 * Reads any past version by git ref. Falls back cleanly if git is unavailable. */
require_once __DIR__ . '/render.php';
final class GitStore {
    public function __construct(private string $root) {}
    public static function gitAvailable(): bool {
        $o = []; $rc = 0; @exec('git --version 2>/dev/null', $o, $rc); return $rc === 0;
    }
    private function git(string $args, int &$rc = null): array {
        $o = []; $rc = 0;
        exec('git -C ' . escapeshellarg($this->root) . ' ' . $args . ' 2>&1', $o, $rc);
        return $o;
    }
    public function init(): void {
        @mkdir($this->root, 0777, true);
        $this->git('init -q');
        $this->git('config user.email tester@congruency.local');
        $this->git('config user.name  Congruency');
    }
    private function rel(string $col, string $id): string { return "$col/$id.json"; }
    public function put(string $col, string $id, array $state, string $msg, int $seq): void {
        $p = "{$this->root}/" . $this->rel($col, $id);
        @mkdir(dirname($p), 0777, true);
        file_put_contents($p, TagStateRenderer::render('json', $state));
        $this->git('add ' . escapeshellarg($this->rel($col, $id)));
        $date = sprintf('2026-01-%02dT00:00:00', $seq);   // fixed => reproducible history
        $env = "GIT_AUTHOR_DATE='$date' GIT_COMMITTER_DATE='$date'";
        exec("$env git -C " . escapeshellarg($this->root) . ' commit -q -m ' . escapeshellarg($msg) . ' 2>&1');
    }
    public function historyCount(string $col, string $id): int {
        return count($this->git('log --oneline -- ' . escapeshellarg($this->rel($col, $id))));
    }
    public function getAt(string $ref, string $col, string $id): array {
        $lines = $this->git('show ' . escapeshellarg("$ref:" . $this->rel($col, $id)));
        return TagStateRenderer::parse('json', implode("\n", $lines));
    }
    public function getHead(string $col, string $id): array { return $this->getAt('HEAD', $col, $id); }
    public function destroy(): void { exec('rm -rf ' . escapeshellarg($this->root)); }
}
