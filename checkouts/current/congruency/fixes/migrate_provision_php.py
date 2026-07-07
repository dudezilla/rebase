#!/usr/bin/env python3
"""migrate_provision_php.py — crank patch (Phase 2): get provision_php working in tools/ + de-anchor.

ONE change: stop anchoring load-bearing code to file-system-repair/ (bug #9).
  1. move checkouts/current/tools/provision_php.py -> checkouts/current/tools/provision_php.py
  2. registry bug_reports -> logs/bug_reports.jsonl ; .gitignore repointed ; logs/ README added
  3. de-anchor every writer under fixes/ + tools/ (hardcoded file-system-repair -> logs / new path)
NO folder deletion (build_inventory/assemble_mono_base + their registry block stay for phase 3).
Self-records to fixes/index.json; Variant-A bug report on exception.
"""
import json
import os
import py_compile
import sys
import time
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))          # fixes/ (when injected)
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

# generic de-anchor replacements applied to every .py under fixes/ + tools/
REPLACEMENTS = [
    ('"logs", "bug_reports.jsonl"', '"logs", "bug_reports.jsonl"'),
    ('"logs/bug_reports.jsonl"', '"logs/bug_reports.jsonl"'),
    ('"checkouts", "current", "tools", "provision_php.py"', '"checkouts", "current", "tools", "provision_php.py"'),
    ('checkouts/current/tools/provision_php.py', 'checkouts/current/tools/provision_php.py'),
]

LOGS_README = """# logs/

Runtime sinks (git-ignored). Holds `bug_reports.jsonl` — the Variant-A bug-report sink named by
`registry.json`'s `bug_reports` key and written by every tool on an unexpected outcome. De-anchored
here from `file-system-repair/` (bug #9) so code resolves the sink via the registry, not a hardcoded
folder.
"""


def apply_replacements(path):
    txt = open(path).read()
    new = txt
    for old, rep in REPLACEMENTS:
        new = new.replace(old, rep)
    if new != txt:
        open(path, "w").write(new)
        return True
    return False


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
             "traceback": tb.strip().splitlines()[-6:], "note": "crank patch: migrate_provision_php (#9)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record(changed):
    entry = {"fix": os.path.basename(__file__),
             "target": "provision_php.py -> tools/ ; bug sink -> logs/ ; de-anchor writers",
             "purpose": "bug #9: get provision_php working in tools/ + de-anchor the bug sink from file-system-repair",
             "changed_files": changed, "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    # 1. move provision_php.py -> tools/
    src = os.path.join(ROOT, "checkouts", "current", "tools", "provision_php.py")
    dst = os.path.join(ROOT, "checkouts", "current", "tools", "provision_php.py")
    if os.path.isfile(src):
        os.rename(src, dst)
    elif not os.path.isfile(dst):
        raise RuntimeError("provision_php.py not found at %s or %s" % (src, dst))

    # 2. de-anchor every writer under fixes/ + tools/ (incl. the just-moved provision_php.py)
    changed = []
    for base in (os.path.join(SOURCE, "fixes"), os.path.join(SOURCE, "tools")):
        for r, _, files in os.walk(base):
            if "__pycache__" in r:
                continue
            for f in files:
                if f.endswith(".py") and apply_replacements(os.path.join(r, f)):
                    changed.append(os.path.relpath(os.path.join(r, f), ROOT))

    # 3. registry bug_reports -> logs/
    reg = os.path.join(ROOT, "registry.json")
    rt = open(reg).read()
    if '"logs/bug_reports.jsonl"' not in rt:
        raise RuntimeError("registry bug_reports anchor not found")
    open(reg, "w").write(rt.replace('"logs/bug_reports.jsonl"', '"logs/bug_reports.jsonl"'))

    # 4. .gitignore repoint
    gi = os.path.join(ROOT, ".gitignore")
    gt = open(gi).read()
    if "/file-system-repair/bug_reports.jsonl" in gt:
        open(gi, "w").write(gt.replace("/file-system-repair/bug_reports.jsonl", "/logs/bug_reports.jsonl"))

    # 5. logs/ home (README tracked; bug_reports.jsonl gitignored)
    logs = os.path.join(ROOT, "logs")
    os.makedirs(logs, exist_ok=True)
    open(os.path.join(logs, "README.md"), "w").write(LOGS_README)

    # 6. compile-check moved provision_php + de-anchored tools
    for p in (dst, os.path.join(SOURCE, "tools", "make_state.py"),
              os.path.join(SOURCE, "tools", "mint_crank.py"), os.path.join(SOURCE, "tools", "serve.py")):
        py_compile.compile(p, doraise=True)

    record(changed)
    print(json.dumps({"ok": True, "moved": "provision_php.py -> checkouts/current/tools/",
                      "changed": changed, "sink": "logs/bug_reports.jsonl"}, indent=2))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        tb = traceback.format_exc()
        bug_report(exc, tb)
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
