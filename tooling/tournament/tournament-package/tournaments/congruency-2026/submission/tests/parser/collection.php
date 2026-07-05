<?php
/* CollectionRenderer — format-at-the-edge for a whole ordered list of tag states
 * (a document), reusing the single-state element renderers. Same internal list
 * renders to XML/HTML/JSON/YAML via a registry; JSON/XML also read back. */
require_once __DIR__ . '/render.php';
final class CollectionRenderer {
    public static function renderers(): array {
        return [
            'xml'  => fn(array $s) => '<tags>'  . implode('', array_map(fn($x)=>TagStateRenderer::render('xml',$x),  $s)) . '</tags>',
            'html' => fn(array $s) => '<div class="tags">' . implode('', array_map(fn($x)=>TagStateRenderer::render('html',$x), $s)) . '</div>',
            'json' => fn(array $s) => json_encode(['tags'=>array_map(fn($x)=>['tag'=>$x['name'],'args'=>array_values($x['args'])], $s)], JSON_UNESCAPED_SLASHES),
            'yaml' => fn(array $s) => self::yaml($s),
        ];
    }
    public static function render(string $fmt, array $states): string {
        $r = self::renderers();
        if (!isset($r[$fmt])) throw new InvalidArgumentException("unknown format: $fmt");
        return $r[$fmt]($states);
    }
    private static function yaml(array $states): string {
        if (!$states) return "tags: []\n";
        $out = "tags:\n";
        foreach ($states as $st) {
            $out .= '- tag: ' . $st['name'] . "\n";
            if (!$st['args']) { $out .= "  args: []\n"; continue; }
            $out .= "  args:\n";
            foreach ($st['args'] as $a) $out .= '    - ' . ($a === '' ? "''" : $a) . "\n";
        }
        return $out;
    }
    /** Readable formats only (json, xml) — reuse the element reverse-readers. */
    public static function parse(string $fmt, string $text): array {
        if ($fmt === 'json') {
            $d = json_decode($text, true);
            return array_map(fn($e)=>['name'=>$e['tag'],'args'=>array_values($e['args'])], $d['tags']);
        }
        if ($fmt === 'xml') {
            preg_match_all('~<tag name=".*?">.*?</tag>~', $text, $m);
            return array_map(fn($frag)=>TagStateRenderer::parse('xml',$frag), $m[0]);
        }
        if ($fmt === 'html') {
            preg_match_all('~<div class="tag" data-name=".*?">.*?</div>~', $text, $m);
            return array_map(fn($frag)=>TagStateRenderer::parse('html',$frag), $m[0]);
        }
        if ($fmt === 'yaml') {
            $states = []; $cur = null;
            foreach (explode("\n", rtrim($text, "\n")) as $ln) {
                if ($ln === 'tags:' || $ln === 'tags: []') continue;
                if (str_starts_with($ln, '- tag: ')) {
                    if ($cur !== null) $states[] = $cur;
                    $cur = ['name' => substr($ln, 7), 'args' => []];
                } elseif ($ln === '  args: []') {
                    /* empty */
                } elseif (str_starts_with($ln, '    - ')) {
                    $v = substr($ln, 6);
                    $cur['args'][] = $v === "''" ? '' : $v;
                }
            }
            if ($cur !== null) $states[] = $cur;
            return $states;
        }
        throw new InvalidArgumentException("collection parse not supported for: $fmt");
    }
}
