#!/usr/bin/env python3
"""doc_tooling.py — doc crank: tooling/README.md (top-level index of the tooling)."""
import json, os, sys, time, traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
FIXES = HERE if os.path.basename(HERE) == "fixes" else os.path.dirname(HERE)
SOURCE = os.path.dirname(FIXES)
INDEX = os.path.join(FIXES, "index.json")


def _root(d=HERE):
    while d != os.path.dirname(d):
        if os.path.isfile(os.path.join(d, "registry.json")):
            return d
        d = os.path.dirname(d)
    return os.path.dirname(os.path.dirname(SOURCE))


ROOT = _root()

README = """# tooling/

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
| `tournament/`, `tournament-lineage/`, `tournament-package/` | the tournament apparatus that selected the winning evolved source (+ `tournament-stepper.js`, `prompt*.txt`). |
| `congruencey/<hash>/` | a commit-hash-named frozen snapshot of the winning source (`2a2e4f8…`). |
| `composer.phar` | PHP dependency manager (for PHPUnit installs). |

Most subdirs carry their own `README.md`. Note: several trees here are duplicated snapshots (see the
drift map in `bugs/drift_report.json`); the live apparatus is under `checkouts/current/`, not here.
"""


def bug_report(exc, tb):
    reg = os.path.join(ROOT, "registry.json")
    rel = "logs/bug_reports.jsonl"
    if os.path.isfile(reg):
        try:
            rel = json.load(open(reg)).get("bug_reports", rel)
        except Exception:
            pass
    path = os.path.join(ROOT, rel)
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "doc crank: doc_tooling"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "tooling/README.md",
             "purpose": "doc: tooling/ top-level index (tests/harness/bugs/coverage/ops/pwdriver/tournament)",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    open(os.path.join(ROOT, "tooling", "README.md"), "w").write(README)
    record()
    print(json.dumps({"ok": True, "wrote": "tooling/README.md"}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
