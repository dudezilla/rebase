#!/usr/bin/env python3
"""assemble_mono_base.py — fold file-system-repair into the b01 mono and verify its base.

Python-only (no shell). Registry-driven: locates registry.json by walking up; THROWS if it
can't see the registry. Any exception is serialized to a timestamped bug-report
(filename/function/time/repro/cause) then re-raised.

Operations:
  1. copy the file-system-repair tool + snapshot into <mono>/file-system-repair/
  2. remove any stray registry.json inside the mono (the registry lives at the workspace root)
  3. verify <mono> base == the required 8 entries
  4. `git add file-system-repair` in the mono
"""
import json
import os
import shutil
import subprocess
import sys
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
REQUIRED_BASE = {".git", "ENTRY_POINT.py", "bugs", "checkouts",
                 "file-system-repair", "mcp", "note-for-claude", "tooling"}


def find_registry(start=HERE):
    d = os.path.abspath(start)
    while True:
        cand = os.path.join(d, "registry.json")
        if os.path.isfile(cand):
            return cand
        parent = os.path.dirname(d)
        if parent == d:
            raise FileNotFoundError("registry.json not found at/above %s — a tool that "
                                    "cannot see the registry must throw" % start)
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


def git(args, cwd):
    return subprocess.run(["git"] + args, cwd=cwd, capture_output=True, text=True)


def main():
    reg = load_registry()
    root = reg["__root__"]
    mono = os.path.join(root, reg.get("paths", {}).get("mono", "b01"))
    if not os.path.isdir(os.path.join(mono, ".git")):
        raise RuntimeError("mono repo not found (no .git) at %s" % mono)

    # 1. copy file-system-repair tool + snapshot into the mono
    src = os.path.join(root, "file-system-repair")
    dst = os.path.join(mono, "file-system-repair")
    os.makedirs(dst, exist_ok=True)
    copied = []
    for name in ("build_inventory.py", "assemble_mono_base.py", "repo_snapshot.json"):
        s = os.path.join(src, name)
        if os.path.isfile(s):
            shutil.copy2(s, os.path.join(dst, name))
            copied.append(name)

    # 2. remove any stray registry inside the mono (registry lives at the workspace root)
    stray = os.path.join(mono, "registry.json")
    removed_stray = os.path.isfile(stray)
    if removed_stray:
        os.remove(stray)

    # 3. verify base
    actual = set(os.listdir(mono))
    missing = sorted(REQUIRED_BASE - actual)
    extra = sorted(actual - REQUIRED_BASE)
    base_ok = not missing and not extra

    # 4. git add file-system-repair
    add = git(["add", "file-system-repair"], mono)

    result = {
        "mono": mono, "copied": copied, "removed_stray_registry": removed_stray,
        "base_ok": base_ok, "missing": missing, "extra": extra,
        "git_add_rc": add.returncode, "git_add_err": add.stderr.strip(),
    }
    print(json.dumps(result, indent=2))
    if not base_ok:
        raise RuntimeError("mono base != required 8 (missing=%s extra=%s)" % (missing, extra))
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
