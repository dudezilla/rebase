<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* DocumentRenderer — the unified "scan content and render it" pass. Each embedded
 * tag is either COMPUTED (its name is an eval op -> tags drive computation) or
 * EXPANDED from a template map, with the output re-scanned recursively. One engine,
 * dispatch entirely by data (goals #2 + #4), depth-guarded self-heal (goal #3). */
require_once __DIR__ . '/scan.php';
require_once __DIR__ . '/compute.php';
require_once __DIR__ . '/syntax.php';
require_once dirname(__DIR__, 2) . '/lib/TagLoader/Arguments/TagArguments.php';
require_once dirname(__DIR__, 2) . '/lib/TagLoader/Parser/Tag_Parser.php';

final class DocumentRenderer {
    /* $handlers: name => callable(TagArguments $args, string $invocation): string —
     * lets REAL invocator tags plug into the engine via a keyed registry (goal #2). */
    public function __construct(
        private array $templates = [],
        private array $handlers = [],
        private TagComputer $computer = new TagComputer(),
        private int $maxDepth = 50
    ) {}

    public function render(string $document, int $depth = 0): string {
        if ($depth > $this->maxDepth)
            throw new RuntimeException("render depth exceeded (cycle?) at $depth");
        foreach (TagScanner::scan($document) as $inv) {
            $parser = Tag_Parser::get_tag_parser($inv);
            $name   = $parser->get_function_name();
            if ($this->computer->isComputable($name)) {            // computational tag (ops + if)
                $value = $this->computer->computeToString($inv);
            } elseif (isset($this->handlers[$name])) {             // live invocator-tag handler
                $out   = ($this->handlers[$name])($parser->get_tag_arguments(), $inv);
                $value = $this->render($out, $depth + 1);          // re-scan its output
            } elseif (array_key_exists($name, $this->templates)) { // template tag
                $value = $this->render($this->templates[$name], $depth + 1);
            } else {
                throw new OutOfBoundsException("unresolved tag: $name");
            }
            $document = str_replace($inv, $value, $document);
        }
        return $document;
    }
}
