#!/usr/bin/env python3
"""build_inventory.py — content-addressed manifest of the FULL workspace.

Registry-driven (per note-for-claude):
  * it locates the config registry `registry.json` by walking UP from its own location;
    if it cannot see the registry it THROWS (a tool that can't find the registry must fail).
  * python-only, no shell.
  * the whole run is wrapped: any exception is serialized to a timestamped bug-report
    (filename, function, time, methods-to-reproduce, possible-cause) and then re-raised —
    the short-term-memory / anti-hallucination workaround.

The manifest covers EVERY file under the registry's `scan_root` (all files, based at root),
skipping the configured dirs and its own outputs, keyed by unique git blob hash:

    <git-hash> -> {"filenames","fullpath","subpath","repo","time-stamp"}
"""
import json
import os
import subprocess
import sys
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))


def find_registry(start=HERE):
    """Walk up from `start` to the first `registry.json`; throw if none is found."""
    d = os.path.abspath(start)
    while True:
        cand = os.path.join(d, "registry.json")
        if os.path.isfile(cand):
            return cand
        parent = os.path.dirname(d)
        if parent == d:
            raise FileNotFoundError(
                "config registry 'registry.json' not found at or above %s — "
                "a tool that cannot see the registry must throw" % start)
        d = parent


def load_registry():
    path = find_registry()
    with open(path) as fh:
        reg = json.load(fh)
    reg["__root__"] = os.path.dirname(path)
    reg["__file__"] = path
    return reg


def bug_report(reg, exc, tb):
    root = (reg or {}).get("__root__", HERE)
    rel = (reg or {}).get("bug_reports", "file-system-repair/bug_reports.jsonl")
    path = os.path.join(root, rel)
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {
        "filename": os.path.basename(last.filename) if last else os.path.basename(__file__),
        "function": last.name if last else "?",
        "time-of-occurance": datetime.now().isoformat(),
        "methods-to-reproduce": "python3 %s" % " ".join([os.path.basename(__file__)] + sys.argv[1:]),
        "possible-cause": "%s: %s" % (type(exc).__name__, exc),
        "traceback": tb.strip().splitlines()[-6:],
    }
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "a") as fh:
        fh.write(json.dumps(entry) + "\n")
    return path


def collect_files(scan_root, skip_dirs, skip_paths):
    skip_dirs = set(skip_dirs or [".git"])
    skip_paths = {os.path.abspath(p) for p in skip_paths}
    out = []
    for root, dirs, names in os.walk(scan_root):
        dirs[:] = [d for d in dirs if d not in skip_dirs]
        for n in names:
            p = os.path.join(root, n)
            ap = os.path.abspath(p)
            if ap in skip_paths:
                continue
            if os.path.islink(p) and not os.path.exists(p):
                continue
            out.append(p)
    return out


def git_hashes(paths):
    """Batch: `git hash-object --stdin-paths` -> one hash per input line, in order."""
    if not paths:
        return []
    proc = subprocess.run(
        ["git", "hash-object", "--stdin-paths"],
        input="\n".join(paths) + "\n",
        stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
    )
    return proc.stdout.split()


def main():
    reg = load_registry()
    root = reg["__root__"]
    fsr = reg.get("file_system_repair", {})
    scan_root = os.path.normpath(os.path.join(root, fsr.get("scan_root", ".")))
    output = os.path.join(root, fsr.get("output", "file-system-repair/repo_snapshot.json"))
    bugs_log = os.path.join(root, reg.get("bug_reports", "file-system-repair/bug_reports.jsonl"))
    skip_dirs = fsr.get("skip_dirs", [".git"])

    files = collect_files(scan_root, skip_dirs, [output, bugs_log])
    hashes = git_hashes(files)
    inventory = {}
    for path, h in zip(files, hashes):
        if not h:
            continue
        rel = os.path.relpath(path, root)                  # based at the registry root
        d = os.path.dirname(rel)
        subpath = "/" if not d else "/%s/" % d.replace(os.sep, "/")
        inventory[h] = {
            "filenames": os.path.basename(path),
            "fullpath": subpath + os.path.basename(path),
            "subpath": subpath,
            "repo": rel.split(os.sep)[0],                  # top-level segment under root
            "time-stamp": (datetime.fromtimestamp(os.path.getmtime(path)).isoformat()
                           if os.path.exists(path) else "unknown"),
        }

    os.makedirs(os.path.dirname(output), exist_ok=True)
    with open(output, "w", encoding="utf-8") as fh:
        json.dump(inventory, fh, indent=2)
    print("registry: %s" % reg["__file__"])
    print("scanned %d files under %s -> %d unique blobs -> %s"
          % (len(files), scan_root, len(inventory), output))
    return inventory


if __name__ == "__main__":
    _reg = None
    try:
        _reg = load_registry()
    except Exception:
        pass
    try:
        main()
    except Exception as exc:  # noqa: BLE001 — serialize then re-raise (the workaround)
        p = bug_report(_reg, exc, traceback.format_exc())
        print("EXCEPTION — bug report written to %s" % p, file=sys.stderr)
        raise
