<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** A comparable element for SortIterator (it sorts on getCompareValue()). */
final class SortEl
{
    public function __construct(public int $v) {}
    public function getCompareValue(): int { return $this->v; }
}

/** Confirms the hand-ported merge sort actually sorts, across many permutations. */
#[Category('sort', 'Hand-ported merge sort correctness')]
final class SortIteratorTest extends TestCase
{
    public static function permutations(): array
    {
        $cases = [];
        for ($n = 1; $n <= 9; $n++) {
            $base = range(1, $n);
            for ($r = 0; $r < $n; $r++) {                 // rotations + their reversals
                $p = $base;
                for ($k = 0; $k < $r; $k++) { $p[] = array_shift($p); }
                $cases["n$n-rot$r"] = [$p];
                $cases["n$n-rot$r-rev"] = [array_reverse($p)];
            }
            $lo = 1; $hi = $n; $zig = [];                  // zig-zag interleave
            while ($lo <= $hi) { $zig[] = $lo++; if ($lo <= $hi) $zig[] = $hi--; }
            $cases["n$n-zig"] = [$zig];
        }
        return $cases;
    }

    #[DataProvider('permutations')]
    public function testSorts(array $input): void
    {
        $want = $input;
        sort($want);
        $this->assertSame($want, $this->runSort($input));
    }

    private function runSort(array $vals): array
    {
        $s = new SortIterator();
        $s->setUnsorted(array_map(fn($v) => new SortEl($v), $vals));
        $out = [];
        $s->reset();
        for ($e = $s->current(); !$s->finished(); $e = $s->next()) {
            if ($e) $out[] = $e->getCompareValue();
        }
        return $out;
    }
}
