<?php
/* TagRegistry: resolves a tag identifier to its invocator descriptor purely via a
 * dictionary loaded from tag-registry.json. Dispatch is data, not hard-coded cases. */
final class TagRegistry {
    private array $map;
    public function __construct(?string $path = null) {
        $path ??= __DIR__ . '/tag-registry.json';
        $this->map = json_decode(file_get_contents($path), true) ?? [];
    }
    public function keys(): array { return array_keys($this->map); }
    public function has(string $id): bool { return isset($this->map[$id]); }
    public function resolve(string $id): array {
        if (!isset($this->map[$id])) throw new OutOfBoundsException("unmapped tag: $id");
        return $this->map[$id];
    }
}
