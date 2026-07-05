<?php
/* Corpus — self-describing dataset graph. The suite reads its OWN description
 * (manifest.json) to discover and self-configure which datasets exist, of what
 * kind, and where. No hard-coded fixture list; adding a dataset = a manifest row.
 * Also detects drift (missing file / undescribed orphan) for self-healing. */
final class Corpus {
    private array $m;
    public function __construct(?string $manifest = null) {
        $this->m = json_decode(file_get_contents($manifest ?? __DIR__ . '/manifest.json'), true);
    }
    public function kinds(): array   { return $this->m['kinds']; }
    public function datasets(): array { return $this->m['datasets']; }
    /** dataset rows of a given kind (self-configuration hook for the runner). */
    public function ofKind(string $kind): array {
        return array_values(array_filter($this->m['datasets'], fn($d) => $d['kind'] === $kind));
    }
    public function load(string $id): array {
        foreach ($this->m['datasets'] as $d) if ($d['id'] === $id)
            return json_decode(file_get_contents(__DIR__ . '/' . $d['file']), true);
        throw new OutOfBoundsException("no dataset: $id");
    }
    /** Reconcile description against reality; returns [missing[], orphan[]]. */
    public function drift(): array {
        $described = array_map(fn($d) => $d['file'], $this->m['datasets']);
        $missing = array_values(array_filter($described, fn($f) => !is_file(__DIR__ . '/' . $f)));
        $present = array_map('basename', glob(__DIR__ . '/*.json'));
        $orphan  = array_values(array_diff($present, $described, ['manifest.json']));
        return [$missing, $orphan];
    }

    /** Re-run a dataset's generator and return its stdout (null if no generator / failed). */
    public function regenerate(string $id, ?string $php = null): ?string {
        $php ??= PHP_BINARY;
        foreach ($this->m['datasets'] as $d) {
            if ($d['id'] !== $id) continue;
            if (empty($d['generator'])) return null;
            $o = []; $rc = 0;
            exec(escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/' . $d['generator']) . ' 2>/dev/null', $o, $rc);
            return $rc === 0 ? implode("\n", $o) : null;
        }
        throw new OutOfBoundsException("no dataset: $id");
    }

    /** Self-heal: regenerate any generator-backed dataset whose file (under $writeDir)
     *  is missing or not valid JSON. Returns the ids that were healed. */
    public function heal(string $writeDir, ?string $php = null): array {
        $healed = [];
        foreach ($this->m['datasets'] as $d) {
            if (empty($d['generator'])) continue;
            $path  = "$writeDir/{$d['file']}";
            $valid = is_file($path) && json_decode((string)@file_get_contents($path), true) !== null;
            if ($valid) continue;
            $regen = $this->regenerate($d['id'], $php);
            if ($regen !== null) {
                @mkdir($writeDir, 0777, true);
                file_put_contents($path, $regen);
                $healed[] = $d['id'];
            }
        }
        return $healed;
    }
}
