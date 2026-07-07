#!/usr/bin/env python3
"""fix_gitignore_pycache.py — crank: stop tracking __pycache__/*.pyc (they leaked to GitHub).

Root cause: .gitignore had no __pycache__/*.pyc rule, so every mint's `git add -A` captured the
bytecode caches (py_compile of the injected patch + module imports). Fix: add the ignore rules
(prevents recurrence) + untrack every tracked __pycache__/*.pyc. Test-first (predict.py): after
untracking, ZERO pycache remain in the index. Records to fixes/index.json; Variant-A bug report on exc.
"""
import importlib.util
import json
import os
import subprocess
import sys
import time
import traceback
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


def git(*a):
    return subprocess.run(["git", *a], cwd=ROOT, capture_output=True, text=True)


def _predict_mod():
    spec = importlib.util.spec_from_file_location("predict", os.path.join(SOURCE, "tools", "predict.py"))
    m = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(m)
    return m


def tracked_pycache():
    out = git("ls-files").stdout.splitlines()
    return [f for f in out if "__pycache__/" in f or f.endswith(".pyc")]


def bug_report(exc, tb):
    path = os.path.join(ROOT, "logs", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: fix_gitignore_pycache"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record(untracked):
    entry = {"fix": os.path.basename(__file__), "target": ".gitignore (+ untrack __pycache__)",
             "purpose": "fix: gitignore __pycache__/*.pyc + untrack %d leaked bytecode caches (root cause: add -A captured them)" % untracked,
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    P = _predict_mod()

    # 1. add the ignore rules (root cause) — this prevents future mints from re-capturing pycache
    gi = os.path.join(ROOT, ".gitignore")
    lines = open(gi).read().splitlines() if os.path.isfile(gi) else []
    for rule in ("__pycache__/", "*.pyc"):
        if rule not in lines:
            lines.append(rule)
    open(gi, "w").write("\n".join(lines) + "\n")

    # 2. untrack every tracked __pycache__/*.pyc (keep the files on disk)
    pyc = tracked_pycache()
    if pyc:
        r = git("rm", "--cached", "--quiet", *pyc)
        if r.returncode != 0:
            raise RuntimeError("git rm --cached failed: %s" % r.stderr[-300:])

    # 3. test-first: the index now has ZERO tracked pycache
    remaining = tracked_pycache()
    v = P.check("no tracked __pycache__/*.pyc remain after untracking", expected=0, actual=len(remaining))
    if v == "REFUTED":
        raise RuntimeError("still %d tracked pycache: %s" % (len(remaining), remaining[:5]))

    record(len(pyc))
    print(json.dumps({"ok": True, "untracked": len(pyc), "remaining": len(remaining)}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
