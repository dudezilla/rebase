<?php
/* FormFlow — server-side form chaining (goal #5): form -> server -> next form.
 * The whole interaction is a keyed transition table; the server computes the next
 * state from (current state, submitted action). No JavaScript, no magic strings —
 * an unknown state or action is an explicit error, never a silent fallthrough. */
final class FormFlow {
    private array $f;
    public function __construct(?string $path = null) {
        $this->f = json_decode(file_get_contents($path ?? __DIR__ . '/form-flow.json'), true);
    }
    public function start(): string { return $this->f['start']; }
    public function isTerminal(string $s): bool { return in_array($s, $this->f['terminal'], true); }
    public function actions(string $s): array {
        if (!isset($this->f['transitions'][$s])) throw new OutOfBoundsException("unknown state: $s");
        return array_keys($this->f['transitions'][$s]);
    }
    /** One server round-trip: resolve the next state from a submitted action. */
    public function next(string $state, string $action): string {
        $t = $this->f['transitions'][$state] ?? null;
        if ($t === null)            throw new OutOfBoundsException("unknown state: $state");
        if (!isset($t[$action]))    throw new OutOfBoundsException("no transition: $state --$action-->");
        return $t[$action];
    }
    /** Walk a whole sequence of submitted actions from the start state. */
    public function walk(array $actions, ?string $from = null): string {
        $s = $from ?? $this->start();
        foreach ($actions as $a) $s = $this->next($s, $a);
        return $s;
    }
}
