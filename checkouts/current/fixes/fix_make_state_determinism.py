#!/usr/bin/env python3
"""fix_make_state_determinism.py — crank patch for bug #3.

ONE change: make `make_state` produce a REPRODUCIBLE database.tar.xz — normalize tar member
metadata (mtime=0, uid/gid=0, uname/gname='', fixed mode; members in sorted order) so
identical logical state => identical blob — and SKIP the state commit when the blob is
unchanged. This de-churns the `state` side-branch (was: a new commit every run).

Patch mechanism: two exact-anchor replacements in checkouts/current/tools/make_state.py,
each asserted to apply once; then compile-check. Self-records to fixes/index.json; files a
Variant-A bug report on any exception.
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
MAKE_STATE = os.path.join(SOURCE, "tools", "make_state.py")
INDEX = os.path.join(FIXES, "index.json")

OLD_TAR = '''    tar_path = os.path.join(tmp, "database.tar.xz")
    with tarfile.open(tar_path, "w:xz") as t:
        t.add(sqlite_tmp, arcname="congruency.sqlite")   # flat members: install_state_db extractall()s to state/
        t.add(seed_tmp, arcname="seed.php")
    return tar_path, tables'''

NEW_TAR = '''    def _norm(ti):
        # reproducible (bug #3): strip mtime/owner so identical state -> identical blob
        ti.mtime = 0
        ti.uid = ti.gid = 0
        ti.uname = ti.gname = ""
        ti.mode = 0o644
        return ti
    tar_path = os.path.join(tmp, "database.tar.xz")
    with tarfile.open(tar_path, "w:xz") as t:
        for src, arc in sorted([(sqlite_tmp, "congruency.sqlite"), (seed_tmp, "seed.php")],
                               key=lambda x: x[1]):
            t.add(src, arcname=arc, filter=_norm)   # flat, normalized members
    return tar_path, tables'''

OLD_BLOB = '''    blob = git(["hash-object", "-w", tar_path]).stdout.strip()
    if not blob:
        raise RuntimeError("git hash-object failed for %s" % tar_path)

    ref = "refs/heads/%s" % side_branch
    head = git(["rev-parse", "--verify", "-q", ref]).stdout.strip() or None'''

NEW_BLOB = '''    blob = git(["hash-object", "-w", tar_path]).stdout.strip()
    if not blob:
        raise RuntimeError("git hash-object failed for %s" % tar_path)

    ref = "refs/heads/%s" % side_branch
    head = git(["rev-parse", "--verify", "-q", ref]).stdout.strip() or None

    # idempotent (bug #3): if identical state is already committed, do NOT churn a new commit
    existing = git(["rev-parse", "-q", "--verify",
                    "%s:%s/database.tar.xz" % (side_branch, crank)]).stdout.strip()
    if existing == blob:
        return {"branch": side_branch, "path": "%s/database.tar.xz" % crank, "blob": blob[:10],
                "commit": "(unchanged)", "parent": (head or "(orphan)")[:10]}'''


def apply_once(text, old, new, label):
    n = text.count(old)
    if n != 1:
        raise RuntimeError("anchor %r matched %d times (expected 1) in make_state.py" % (label, n))
    return text.replace(old, new)


def bug_report(exc, tb):
    def root(d):
        while d != os.path.dirname(d):
            if os.path.isfile(os.path.join(d, "registry.json")):
                return d
            d = os.path.dirname(d)
        return os.path.dirname(os.path.dirname(SOURCE))
    path = os.path.join(root(HERE), "file-system-repair", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank patch: fix_make_state_determinism (#3)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "checkouts/current/tools/make_state.py",
             "purpose": "bug #3: reproducible database.tar.xz + idempotent state commit (de-churn state branch)",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    src = open(MAKE_STATE).read()
    if "_norm(ti)" in src:
        raise RuntimeError("make_state.py already patched for determinism")
    src = apply_once(src, OLD_TAR, NEW_TAR, "tar-build")
    src = apply_once(src, OLD_BLOB, NEW_BLOB, "blob-idempotency")
    open(MAKE_STATE, "w").write(src)
    py_compile.compile(MAKE_STATE, doraise=True)   # unexpected outcome if it won't compile
    record()
    print(json.dumps({"ok": True, "patched": "make_state.py", "change": "reproducible+idempotent state"}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        tb = traceback.format_exc()
        bug_report(exc, tb)
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
