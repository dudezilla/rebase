<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* TagExpander — models Tag_Wrapper::execute_all_tags: recursively replace every
 * embedded tag with its rendered output, re-scanning that output for child tags.
 * Tag output is resolved through a template map (goal #2, no magic strings). A
 * depth guard self-heals against runaway recursion instead of hanging. */
require_once __DIR__ . '/scan.php';
require_once __DIR__ . '/syntax.php';
require_once dirname(__DIR__, 2) . '/lib/TagLoader/Arguments/TagArguments.php';
require_once dirname(__DIR__, 2) . '/lib/TagLoader/Parser/Tag_Parser.php';

final class TagExpander {
    public function __construct(private array $templates, private int $maxDepth = 50) {}

    public function expand(string $document, int $depth = 0): string {
        if ($depth > $this->maxDepth)
            throw new RuntimeException("expansion depth exceeded (cycle?) at depth $depth");
        foreach (TagScanner::scan($document) as $inv) {
            $name = Tag_Parser::get_tag_parser($inv)->get_function_name();
            if (!array_key_exists($name, $this->templates))
                throw new OutOfBoundsException("no template for tag: $name");
            $rendered = $this->expand($this->templates[$name], $depth + 1);  // recurse into output
            $document = str_replace($inv, $rendered, $document);
        }
        return $document;
    }
}
