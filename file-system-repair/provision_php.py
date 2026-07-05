#!/usr/bin/env python3
"""provision_php.py — new-tree reference-server recipe: provision the php runtime.

Fixes bug: "no php binary in the new tree". The mono ships NO binaries (they don't belong
in git); instead this python recipe places a php binary into
<mono>/tooling/congruencey-harness/php/php from an available source, and ensures it is
git-ignored. The recipe is committed; the binary is not.

python-only, registry-driven (throws if it can't see registry.json), auto-bug-report on
exception.
"""
import json
import os
import shutil
import stat
import subprocess
import sys
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
DEST_REL = os.path.join("tooling", "congruencey-harness", "php", "php")
SOURCE_CANDIDATES = [
    "/home/notificationsforsteven/congruencey-harness/php/php",
    "/home/notificationsforsteven/b01/tooling/congruencey-harness/php/php",
]


def find_registry(start=HERE):
    d = os.path.abspath(start)
    while True:
        cand = os.path.join(d, "registry.json")
        if os.path.isfile(cand):
            return cand
        parent = os.path.dirname(d)
        if parent == d:
            raise FileNotFoundError("registry.json not found at/above %s" % start)
        d = parent


def load_registry():
    path = find_registry()
    reg = json.load(open(path))
    reg["__root__"] = os.path.dirname(path)
    reg["__file__"] = path
    return reg


def bug_report(reg, exc, tb):
    root = (reg or {}).get("__root__", HERE)
    path = os.path.join(root, (reg or {}).get("bug_reports", "file-system-repair/bug_reports.jsonl"))
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {
        "filename": os.path.basename(last.filename) if last else os.path.basename(__file__),
        "function": last.name if last else "?",
        "time-of-occurance": datetime.now().isoformat(),
        "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
        "possible-cause": "%s: %s" % (type(exc).__name__, exc),
        "traceback": tb.strip().splitlines()[-6:],
    }
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "a") as fh:
        fh.write(json.dumps(entry) + "\n")
    return path


def ensure_gitignore(mono, rel):
    gi = os.path.join(mono, ".gitignore")
    line = "/" + rel.replace(os.sep, "/")
    existing = open(gi).read().splitlines() if os.path.isfile(gi) else []
    if line not in existing:
        with open(gi, "a") as fh:
            fh.write(("" if not existing or existing[-1] == "" else "\n") + line + "\n")
    return gi


def main():
    reg = load_registry()
    root = reg["__root__"]
    mono = os.path.join(root, reg.get("paths", {}).get("mono", "b01"))

    src = next((c for c in SOURCE_CANDIDATES if os.path.isfile(c)), None)
    if not src:
        raise FileNotFoundError("no php binary available to provision from: %s" % SOURCE_CANDIDATES)

    dest = os.path.join(mono, DEST_REL)
    os.makedirs(os.path.dirname(dest), exist_ok=True)
    shutil.copy2(src, dest)
    os.chmod(dest, os.stat(dest).st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)

    gi = ensure_gitignore(mono, DEST_REL)
    # stage the recipe + gitignore (the binary is ignored, not tracked)
    subprocess.run(["git", "add", ".gitignore", "file-system-repair/provision_php.py"],
                   cwd=mono, capture_output=True, text=True)

    result = {
        "mono": mono, "provisioned_from": src, "php": os.path.relpath(dest, mono),
        "php_size_bytes": os.path.getsize(dest),
        "gitignored": "/" + DEST_REL.replace(os.sep, "/"),
        "committed_artifacts": ["file-system-repair/provision_php.py", ".gitignore"],
    }
    print(json.dumps(result, indent=2))
    return result


if __name__ == "__main__":
    _reg = None
    try:
        _reg = load_registry()
    except Exception:
        pass
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        p = bug_report(_reg, exc, traceback.format_exc())
        print("EXCEPTION — bug report -> %s" % p, file=sys.stderr)
        raise
