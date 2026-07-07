<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/* TagEvaluator — a keyed evaluation core with named recursive functions.
 *
 * Every node {op, args|value|name} is dispatched through an OPERATOR MAP, never a
 * hard-coded switch. Nesting gives composition; `if` gives branching; `var`/`call`
 * over a definition environment give recursion — together a Turing-complete core.
 * Tags drive computation; the ops and function bodies are data (goal #4, goal #2).
 */
final class TagEvaluator {
    /** op-key => reducer over already-evaluated integer operands. */
    private array $ops;
    public function __construct() {
        $this->ops = [
            'add' => fn(array $a) => array_sum($a),
            'sub' => fn(array $a) => array_reduce(array_slice($a,1), fn($c,$x)=>$c-$x, $a[0]),
            'mul' => fn(array $a) => array_reduce($a, fn($c,$x)=>$c*$x, 1),
            'div' => fn(array $a) => intdiv($a[0], $a[1]),
            'mod' => fn(array $a) => $a[0] % $a[1],
            'neg' => fn(array $a) => -$a[0],
            'eq'  => fn(array $a) => $a[0] === $a[1] ? 1 : 0,
            'lt'  => fn(array $a) => $a[0] <  $a[1] ? 1 : 0,
            'seq' => fn(array $a) => end($a),
            'max' => fn(array $a) => max($a),
            'min' => fn(array $a) => min($a),
            'abs' => fn(array $a) => abs($a[0]),
            'pow' => fn(array $a) => (int)($a[0] ** $a[1]),
            'inc' => fn(array $a) => $a[0] + 1,
            'dec' => fn(array $a) => $a[0] - 1,
        ];
    }
    public function opKeys(): array {
        return array_merge(['int','if','var','call'], array_keys($this->ops));
    }
    /** True iff $k is a data-table arithmetic/logic op (excludes structural int/if/var/call). */
    public function hasOp(string $k): bool { return isset($this->ops[$k]); }

    /** Evaluate a bare expression node (no defs, no vars). */
    public function eval(array $node): int {
        return $this->evalNode($node, [], []);
    }

    /** Run a program: {defs:{name:{params,body}}, main:node} -> int. */
    public function run(array $program): int {
        $defs = $program['defs'] ?? [];
        return $this->evalNode($program['main'], [], $defs);
    }

    /** Core: evaluate $node under variable bindings $vars and function table $defs. */
    private function evalNode(array $node, array $vars, array $defs): int {
        $op = $node['op'] ?? null;

        if ($op === 'int') return (int)$node['value'];                 // literal
        if ($op === 'var') {                                           // variable ref
            if (!array_key_exists($node['name'], $vars))
                throw new OutOfBoundsException("unbound var: {$node['name']}");
            return $vars[$node['name']];
        }
        if ($op === 'if') {                                            // lazy branch
            [$c,$t,$e] = $node['args'];
            return $this->evalNode($c,$vars,$defs) !== 0
                 ? $this->evalNode($t,$vars,$defs)
                 : $this->evalNode($e,$vars,$defs);
        }
        if ($op === 'call') {                                          // function application
            $fn = $defs[$node['name']] ?? null;
            if ($fn === null) throw new OutOfBoundsException("undefined fn: {$node['name']}");
            $argv = array_map(fn($n) => $this->evalNode($n,$vars,$defs), $node['args']);
            $frame = [];
            foreach ($fn['params'] as $i => $p) $frame[$p] = $argv[$i];
            return $this->evalNode($fn['body'], $frame, $defs);        // fresh scope: recursion-safe
        }
        if (!isset($this->ops[$op])) throw new OutOfBoundsException("unknown op: " . var_export($op,true));
        $operands = array_map(fn($n) => $this->evalNode($n,$vars,$defs), $node['args']);
        return ($this->ops[$op])($operands);
    }
}
