# congruencey-tests

The mechanically-verifiable test suite for the resurrected congruency CMS — one
command, deterministic assertions, machine-readable output.

```bash
./verify              # run everything; exit 0 iff all suites pass
```

`verify` starts/seeds the harness, drives the attack loop, then runs:

| Suite | What it verifies | How |
|-------|------------------|-----|
| **PHPUnit** (`tests/`) | forms + ConfigForm fixes, ValidateFields behaviour, SortIterator correctness (117 cases), attack-verdict baseline | `vendor/bin/phpunit`, JUnit XML in `artifacts/` |
| **Bug catalog** | all 15 catalogued bugs still reproduce against the pinned submodule | `../congruencey-bugs/run` |
| **Branch coverage** | `ValidateFields` 100% (6/6), and `DataConnection::quote` 1/2 (a provably unreachable dead branch) | `../coverage/branch_test*.php` |

Everything returns a process exit code; `verify` aggregates them.

## Dependencies (all durable, under `~`)
- `congruencey-harness/` — boots the app (static PHP 8.3, shim, seeded SQLite). Provides `php/php` and the bootstrap this suite loads.
- `congruencey/` — the code under test (`main`, with the fixes).
- `congruencey-bugs/` — the bug catalog (pinned submodule at the original commit).
- `coverage/` — the tokenizer-based branch-coverage tool (no xdebug/pcov is loadable on the static build).
- `pwdriver/` — the Playwright attack loop that populates the telemetry DB.

## Reproducing
```bash
composer install         # restores PHPUnit (version pinned in composer.lock)
./verify
```

Coverage note: this static PHP build cannot `dlopen` a coverage extension
(no `phpize`/headers, no dynamic ext loading), so branch coverage is measured
by the custom tokenizer instrumenter in `../coverage/`, not xdebug/pcov.
