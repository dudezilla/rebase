<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* TagBuilder — the inverse of parsing: assemble a "<<<Name(a)(b)>>>" invocation
 * from a name and source-order args, using the delimiter constants (no magic
 * strings, goal #2). Parsing a built tag returns the args REVERSED (stack order). */
require_once __DIR__ . '/syntax.php';
final class TagBuilder {
    public static function build(string $name, array $sourceArgs): string {
        $body = $name;
        foreach ($sourceArgs as $a) $body .= "($a)";
        return KEY_PREFIX . $body . KEY_SUFFIX;
    }
}
