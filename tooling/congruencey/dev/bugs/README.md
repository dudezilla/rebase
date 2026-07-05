# congruency-bugs

A side repository that catalogs bugs in [`congruency`](https://github.com/dudezilla/congruency)
— a PHP content-management system (Steven Peterson, © 2006, GPLv2) — and, for each one, ships a
**runnable function that reproduces it** against the real code on modern PHP.

The code under test is pinned here as a git submodule at [`vendor/congruency`](vendor/congruency),
so every reproduction runs the genuine 2006 classes, not a paraphrase.

## Quick start

```bash
git clone <this repo> && cd congruency-bugs
git submodule update --init          # fetch the code under test
./run                                # reproduce every bug, print a report
./run BUG-01 BUG-06                  # reproduce specific bugs
```

`./run` needs a **PHP 8** interpreter. It looks for `$CONGRUENCEY_PHP`, then `php` on `PATH`,
then a local static build at `vendor/php/php`. A static binary (no system install/root needed)
is available from <https://dl.static-php.dev/static-php-cli/common/>.

Exit code is the number of bugs that did **not** reproduce (`0` = all reproduced).

## Layout

```
bugs.json          machine-readable catalog: id, severity, location, repro, success signature
BUGS.md            human-readable catalog + root-cause notes
harness.php        runs each repro in its own PHP process, checks the success signature
run                bash entrypoint (locates PHP, ensures submodule, runs the harness)
repro/             one self-contained reproduce() per bug — also runnable directly:
                     php repro/bug01_catalog_sql_injection.php
src/
  bootstrap.php    boots the framework on PHP 8 (shims + config + autoloader), seeds a fixture DB
  shim.php         emulates the removed mysql_* API over PDO+SQLite; get_magic_quotes_gpc() stub
  AutoLoader.php   neutralized stub (PHP 8 forbids declaring __autoLoad())
vendor/congruency submodule: the code under test (pinned)
```

## How a reproduction works

Each `repro/bugNN_*.php` requires `src/bootstrap.php`, then defines and calls `reproduce()`,
which drives the real classes until the bug fires. Crashers catch the `Throwable` and print
`expected:` / `observed:` / `REPRODUCED`; the data-leak and behavioural bugs print a `CONFIRMED`
line instead. The harness treats a bug as reproduced when its `success` signature (from
`bugs.json`) appears in the child process output.

The uncatchable one (BUG-06, unbounded recursion → memory exhaustion) is run with a low
`memory_limit` in its own process, and "Allowed memory size … exhausted" counts as the repro.

## Adding a bug

1. Write `repro/bugNN_shortname.php` — `require` the bootstrap, add a `reproduce()`, call it,
   and print a distinctive success line.
2. Add an entry to `bugs.json` with its `success` signature (and any `env` / `php_flags`).
3. `./run BUG-NN` to confirm.

See [`BUGS.md`](BUGS.md) for the full catalog and root-cause analysis.
