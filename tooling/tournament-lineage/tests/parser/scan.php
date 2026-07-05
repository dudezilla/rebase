<?php
/* TagScanner — the content-scanning stage: find every tag invocation embedded in
 * a document body. Mirrors Tag_Wrapper::identify_tag exactly, composing the match
 * pattern from the syntax constants (no baked-in regex literal). */
require_once __DIR__ . '/syntax.php';
final class TagScanner {
    public static function pattern(): string {
        return TAG_KEY_PREFIX . FUNCTION_NAME . FUNCTION_ARGUMENTS . TAG_KEY_SUFFIX;
    }
    public static function scan(string $document): array {
        preg_match_all(self::pattern(), $document, $m);
        return $m[0];
    }
}
