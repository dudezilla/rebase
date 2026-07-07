<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* Format-at-the-edge renderer. A parsed tag's canonical state {name, args[]} is
 * rendered to XML/HTML/JSON/YAML by looking the format up in a registry map — the
 * rendering logic never bakes in a format, and no magic strings select behavior. */
final class TagStateRenderer {
    /** Registry: format-key => renderer callable. Add a format = add a map entry. */
    public static function renderers(): array {
        return [
            'xml'  => fn(array $s) => self::xml($s),
            'html' => fn(array $s) => self::html($s),
            'json' => fn(array $s) => self::json($s),
            'yaml' => fn(array $s) => self::yaml($s),
        ];
    }
    public static function render(string $format, array $state): string {
        $r = self::renderers();
        if (!isset($r[$format])) throw new InvalidArgumentException("unknown format: $format");
        return $r[$format]($state);
    }
    private static function xml(array $s): string {
        $args = '';
        foreach ($s['args'] as $a) $args .= '<arg>' . htmlspecialchars($a, ENT_XML1) . '</arg>';
        return '<tag name="' . htmlspecialchars($s['name'], ENT_XML1) . '">' . $args . '</tag>';
    }
    private static function html(array $s): string {
        $args = '';
        foreach ($s['args'] as $a) $args .= '<span class="arg">' . htmlspecialchars($a, ENT_QUOTES) . '</span>';
        return '<div class="tag" data-name="' . htmlspecialchars($s['name'], ENT_QUOTES) . '">' . $args . '</div>';
    }
    private static function json(array $s): string {
        return json_encode(['tag' => $s['name'], 'args' => array_values($s['args'])],
                           JSON_UNESCAPED_SLASHES);
    }
    private static function yaml(array $s): string {
        $out = 'tag: ' . $s['name'] . "\n";
        if (!$s['args']) return $out . "args: []\n";
        $out .= "args:\n";
        foreach ($s['args'] as $a) $out .= '  - ' . ($a === '' ? "''" : $a) . "\n";
        return $out;
    }

    /* --- reverse: ingest a rendered string back to canonical state ---
     * Same idea, other direction: format is chosen at the edge via a parser
     * registry, so render()/parse() are an inverse pair for our emitted subset. */
    public static function parsers(): array {
        return [
            'xml'  => fn(string $t) => self::fromXml($t),
            'html' => fn(string $t) => self::fromHtml($t),
            'json' => fn(string $t) => self::fromJson($t),
            'yaml' => fn(string $t) => self::fromYaml($t),
        ];
    }
    public static function parse(string $format, string $text): array {
        $p = self::parsers();
        if (!isset($p[$format])) throw new InvalidArgumentException("unknown format: $format");
        return $p[$format]($text);
    }
    private static function fromJson(string $t): array {
        $d = json_decode($t, true);
        return ['name' => $d['tag'], 'args' => array_values($d['args'])];
    }
    private static function fromXml(string $t): array {
        preg_match('~<tag name="(.*?)">~', $t, $m);
        preg_match_all('~<arg>(.*?)</arg>~', $t, $am);
        return ['name' => htmlspecialchars_decode($m[1], ENT_XML1),
                'args' => array_map(fn($a) => htmlspecialchars_decode($a, ENT_XML1), $am[1])];
    }
    private static function fromHtml(string $t): array {
        preg_match('~data-name="(.*?)"~', $t, $m);
        preg_match_all('~<span class="arg">(.*?)</span>~', $t, $am);
        return ['name' => htmlspecialchars_decode($m[1], ENT_QUOTES),
                'args' => array_map(fn($a) => htmlspecialchars_decode($a, ENT_QUOTES), $am[1])];
    }
    private static function fromYaml(string $t): array {
        $lines = explode("\n", rtrim($t, "\n"));
        $name = ''; $args = [];
        foreach ($lines as $ln) {
            if (str_starts_with($ln, 'tag: ')) { $name = substr($ln, 5); continue; }
            if ($ln === 'args: []') { $args = []; continue; }
            if (str_starts_with($ln, '  - ')) {
                $v = substr($ln, 4);
                $args[] = $v === "''" ? '' : $v;
            }
        }
        return ['name' => $name, 'args' => $args];
    }
}
