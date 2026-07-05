# Install & run

## 0. Re-hydrate
    git clone tournament.bundle tournament && cd tournament

## 1. Unpack a nested repo (example: the code lineage)
    git clone tournaments/congruency-2026/lineage/tournament-lineage.bundle lineage
    git -C lineage log --oneline           # original -> refactors -> submission
    git -C lineage diff refactors submission

## 2. Prerequisite: a PHP 8 interpreter
The tournament ran on a static PHP 8 build (~7 MB) that is NOT shipped in this package
(binaries don't belong in git history). Any PHP 8.x works. Point tooling at it via:
    export CONGRUENCEY_PHP=/path/to/php

## 3. Rebuild the database (no live DB is shipped)
    cd tournaments/congruency-2026
    $CONGRUENCEY_PHP database/seed.php          # regenerates congruency.sqlite from scratch
    #   or load the captured content dump into a fresh sqlite file:
    #   sqlite3 congruency.sqlite < database/congruency.dump.sql

## 4. Run the submission's oracle (self-contained, no DB/server/JS)
    cd tournaments/congruency-2026/submission/tests/parser
    $CONGRUENCEY_PHP run.php                     # exit 0 iff all assertions pass

## 5. Serve the redesigned showcase (server-side, zero JavaScript)
    cd tournaments/congruency-2026/submission
    $CONGRUENCEY_PHP -S 0.0.0.0:8901 showcase/router.php
    #   open http://localhost:8901/

## 6. Unpack the apparatus repos as needed
    git clone tournaments/congruency-2026/apparatus/harness.bundle  harness
    git clone tournaments/congruency-2026/apparatus/tests.bundle    tests
    git clone tournaments/congruency-2026/apparatus/bugs.bundle     bugs
