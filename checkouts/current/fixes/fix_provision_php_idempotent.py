#!/usr/bin/env python3
"""fix_provision_php_idempotent.py — crank patch for bug #4.

ONE change: make `provision_php` idempotent — if a WORKING php is already at dest
(`dest -v` returns 0), skip re-acquisition entirely instead of re-downloading ~12MB every
run. The network-fetch fallback is preserved for a genuinely fresh clone.

Patch mechanism: one exact-anchor replacement in file-system-repair/provision_php.py, then
compile-check. Self-records to fixes/index.json; Variant-A bug report on any exception.
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


PROVISION = os.path.join(_root(), "file-system-repair", "provision_php.py")

OLD = '''    cfg = reg.get("php_provision", {}) or {}
    sources = cfg.get("sources", DEFAULT_SOURCES)
    src = next((c for c in sources if os.path.isfile(c)), None)

    if src and os.path.abspath(src) != os.path.abspath(dest):
        shutil.copy2(src, dest)
        how = "copied from %s" % src
    elif src:
        how = "already in place (%s)" % src
    else:
        how = _provision_from_download(cfg.get("download", DEFAULT_DOWNLOAD), dest)

    os.chmod(dest, os.stat(dest).st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)'''

NEW = '''    # idempotent (bug #4): a WORKING php already at dest -> skip re-acquisition (the
    # network-fetch fallback below still runs on a genuinely fresh clone).
    if os.path.isfile(dest) and subprocess.run([dest, "-v"], capture_output=True).returncode == 0:
        how = "already provisioned (%s)" % os.path.relpath(dest, mono)
    else:
        cfg = reg.get("php_provision", {}) or {}
        sources = cfg.get("sources", DEFAULT_SOURCES)
        src = next((c for c in sources if os.path.isfile(c)), None)
        if src and os.path.abspath(src) != os.path.abspath(dest):
            shutil.copy2(src, dest)
            how = "copied from %s" % src
        elif src:
            how = "already in place (%s)" % src
        else:
            how = _provision_from_download(cfg.get("download", DEFAULT_DOWNLOAD), dest)
        os.chmod(dest, os.stat(dest).st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)'''


def bug_report(exc, tb):
    path = os.path.join(_root(), "file-system-repair", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank patch: fix_provision_php_idempotent (#4)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "file-system-repair/provision_php.py",
             "purpose": "bug #4: provision_php idempotent — skip re-download when a working php is already at dest",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    src = open(PROVISION).read()
    if "already provisioned" in src:
        raise RuntimeError("provision_php.py already patched for idempotency")
    n = src.count(OLD)
    if n != 1:
        raise RuntimeError("anchor matched %d times (expected 1) in provision_php.py" % n)
    open(PROVISION, "w").write(src.replace(OLD, NEW))
    py_compile.compile(PROVISION, doraise=True)
    record()
    print(json.dumps({"ok": True, "patched": "provision_php.py", "change": "idempotent (skip re-download)"}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        tb = traceback.format_exc()
        bug_report(exc, tb)
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
