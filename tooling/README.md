# tooling/

Test, harness, and evolution tooling for the CMS. Convention: **python drives, php runs the app**
(the old bash `verify` is retired as `congruencey-tests/verify.bash.retired`).

| dir / file | what |
|---|---|
| `congruencey-tests/` | the test suite: `verify` (python — stand-up + bug-catalog + branch-coverage), PHPUnit `tests/`, `phpunit.xml`. |
| `congruencey-harness/` | the php-web harness: the provisioned `php/php` binary (gitignored), `seed.php`, `router.php`, `shim.php`, `telemetry.php`. |
| `congruencey-bugs/` | the 2006 CMS **bug catalog**: `bugs.json` (15 defects), `harness.php` + `run` (reproduce each), `repro/`, `src/`. |
| `coverage/` | branch-coverage instruments: `coverage.php`, `branch_test.php`, `branch_test2.php` (repathed to `checkouts/current`). |
| `ops/` | python ops (`consolidate_ops`, `propagate_version`, `refresh_bundles`, `run_op`) + a `bug_reports.jsonl`. |
| `pwdriver/` | the headless-browser attack driver (`attack.js`) feeding the adversarial telemetry. |
| `tournament-lineage/`, `tournament-package/` | the tournament apparatus that selected the winning evolved source (+ `tournament-stepper.js`, `prompt*.txt`). |
| `congruencey/<hash>/` | a commit-hash-named frozen snapshot of the winning source (`2a2e4f8…`). |
| `composer.phar` | PHP dependency manager (for PHPUnit installs). |

Most subdirs carry their own `README.md`. Note: several trees here are duplicated snapshots (see the
drift map in `bugs/drift_report.json`); the live apparatus is under `checkouts/current/`, not here.
