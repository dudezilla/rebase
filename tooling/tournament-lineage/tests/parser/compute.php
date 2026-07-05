<?php
/* TagComputer — lets a TAG drive computation (goals #2 + #4). A computational tag
 * like <<<add(2)(3)>>> is parsed, its name dispatched through the evaluator's
 * op-table, and its args (un-reversed to source order) become integer operands.
 * Non-op names and non-integer args are explicit errors, never silent. */
require_once __DIR__ . '/syntax.php';
require_once __DIR__ . '/eval.php';
require_once dirname(__DIR__, 2) . '/lib/TagLoader/Arguments/TagArguments.php';
require_once dirname(__DIR__, 2) . '/lib/TagLoader/Parser/Tag_Parser.php';

final class TagComputer {
    public function __construct(private TagEvaluator $ev = new TagEvaluator()) {}

    public function compute(string $tag): int {
        $p    = Tag_Parser::get_tag_parser($tag);
        $name = $p->get_function_name();
        $args = array_reverse(array_values($p->get_tag_arguments()->getArguments() ?? []));
        foreach ($args as $a) {
            if (!preg_match('/^-?\d+$/', $a)) throw new InvalidArgumentException("non-integer arg: '$a'");
        }
        $node = ['op' => $name, 'args' => array_map(fn($a) => ['op'=>'int','value'=>(int)$a], $args)];
        return $this->ev->eval($node);            // throws OutOfBounds on non-op name
    }
    public function isOp(string $name): bool { return $this->ev->hasOp($name); }
    /** Names computable at the tag surface: arithmetic ops plus the structural `if`. */
    public function isComputable(string $name): bool { return $this->ev->hasOp($name) || $name === 'if'; }
    public function computeToString(string $tag): string { return (string)$this->compute($tag); }
}
