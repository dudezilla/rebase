<?php
/*
 * A minimal branch-coverage instrumenter built on PHP's tokenizer (no xdebug).
 * It inserts a probe at the entry of each control-flow block (if / elseif / else
 * / for / while / foreach / case / default), so running instrumented code records
 * which branches executed. Class/function/interface guard-ifs are skipped.
 *
 * Limitation: it measures BLOCK entry. For if/else both edges are blocks, giving
 * true branch coverage; for an if with no else, only the taken edge is a block
 * (the false edge is a fall-through). Ternaries and &&/|| short-circuits are not
 * instrumented. Good enough to measure real coverage of straight branch logic.
 */
class Cov {
    public static $hits = [];
    public static function hit($id) { self::$hits[$id] = (self::$hits[$id] ?? 0) + 1; }
    public static function reset() { self::$hits = []; }
}

function cov_instrument($src, &$branches) {
    $toks = token_get_all($src);
    $n = count($toks);
    $out = '';
    $branches = [];
    $id = 0;
    $pending = null;
    $isWs = fn($t) => is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]);
    $peek = function ($j) use ($toks, $n, $isWs) {
        for ($k = $j + 1; $k < $n; $k++) if (!$isWs($toks[$k])) return $k;
        return -1;
    };
    for ($i = 0; $i < $n; $i++) {
        $t = $toks[$i];
        $s = is_array($t) ? $t[1] : $t;
        $out .= $s;
        if ($pending === null) {
            if (is_array($t)) {
                $k = $t[0];
                if (in_array($k, [T_IF, T_ELSEIF, T_WHILE, T_FOR, T_FOREACH])) {
                    $pending = ['id' => $id, 'type' => token_name($k), 'line' => $t[2],
                                'mode' => 'paren', 'depth' => 0, 'seen' => false, 'cond' => ''];
                } elseif ($k === T_ELSE) {
                    $p = $peek($i);
                    if (!($p >= 0 && is_array($toks[$p]) && $toks[$p][0] === T_IF)) {   // not "else if"
                        $pending = ['id' => $id, 'type' => 'ELSE', 'line' => $t[2], 'mode' => 'brace'];
                    }
                } elseif ($k === T_CASE || $k === T_DEFAULT) {
                    $pending = ['id' => $id, 'type' => token_name($k), 'line' => $t[2], 'mode' => 'colon'];
                }
            }
        } else {
            if ($pending['mode'] === 'colon') {
                if ($s === ':') { $branches[$pending['id']] = ['line' => $pending['line'], 'type' => $pending['type']];
                    $out .= " \\Cov::hit({$pending['id']}); "; $id++; $pending = null; }
            } elseif ($pending['mode'] === 'paren') {
                if ($s === '(') { $pending['depth']++; $pending['seen'] = true; $pending['cond'] .= $s; }
                elseif ($s === ')') { $pending['depth']--; $pending['cond'] .= $s; }
                elseif ($s === '{' && $pending['seen'] && $pending['depth'] === 0) {
                    if (preg_match('/class_exists|function_exists|interface_exists/', $pending['cond'])) {
                        $pending = null;                              // skip guard-ifs
                    } else {
                        $branches[$pending['id']] = ['line' => $pending['line'], 'type' => $pending['type']];
                        $out .= " \\Cov::hit({$pending['id']}); "; $id++; $pending = null;
                    }
                } elseif ($pending['seen'] && $pending['depth'] > 0) { $pending['cond'] .= $s; }
            } elseif ($pending['mode'] === 'brace') {
                if ($s === '{') { $branches[$pending['id']] = ['line' => $pending['line'], 'type' => $pending['type']];
                    $out .= " \\Cov::hit({$pending['id']}); "; $id++; $pending = null; }
            }
        }
    }
    return $out;
}

function cov_report($branches) {
    $total = count($branches); $hit = 0;
    foreach ($branches as $bid => $b) if (!empty(Cov::$hits[$bid])) $hit++;
    printf("\nBranch (block) coverage: %d/%d (%.0f%%)\n", $hit, $total, $total ? 100 * $hit / $total : 0);
    foreach ($branches as $bid => $b) {
        $h = Cov::$hits[$bid] ?? 0;
        printf("  [%s] %-8s @ line %-3d  %s\n", $h ? 'x' : ' ', $b['type'], $b['line'],
            $h ? "covered ($h hit" . ($h == 1 ? '' : 's') . ")" : 'MISSED');
    }
    return [$hit, $total];
}
