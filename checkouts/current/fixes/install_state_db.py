#!/usr/bin/env python3
"""install_state_db.py — ratchet link: install the SQL DB at its NEW location.

Per spec the database now lives under checkouts/state/. It ships COMPRESSED
(database.tar.xz, the committed recipe); no binaries/sqlite go in git. This link
uncompresses it in place, verifies the sqlite actually opens and carries tables,
and git-ignores the uncompressed artifacts.

python-only, self-verifying, permanent, recorded in fixes/index.json.
"""
import json
import os
import sqlite3
import sys
import tarfile
import time
import traceback

HERE = os.path.dirname(os.path.abspath(__file__))                 # checkouts/current/fixes
SOURCE = os.path.dirname(HERE)                                    # checkouts/current
MONO = os.path.dirname(os.path.dirname(SOURCE))                   # b01
STATE = os.path.join(SOURCE, "state")                            # checkouts/current/state
TAR = os.path.join(STATE, "database.tar.xz")
SQLITE = os.path.join(STATE, "congruency.sqlite")
INDEX = os.path.join(HERE, "index.json")


def ensure_gitignore(paths):
    gi = os.path.join(MONO, ".gitignore")
    lines = open(gi).read().splitlines() if os.path.isfile(gi) else []
    added = []
    for p in paths:
        rel = "/" + os.path.relpath(p, MONO).replace(os.sep, "/")
        if rel not in lines:
            lines.append(rel); added.append(rel)
    if added:
        with open(gi, "w") as fh:
            fh.write("\n".join(lines) + "\n")
    return added


def extract():
    if not os.path.isfile(TAR):
        raise FileNotFoundError("no database.tar.xz at %s" % TAR)
    members = []
    with tarfile.open(TAR, "r:xz") as t:
        for m in t.getmembers():
            members.append(m.name)
        t.extractall(STATE)
    return members


def verify():
    if not os.path.isfile(SQLITE):
        raise FileNotFoundError("congruency.sqlite not extracted at %s" % SQLITE)
    con = sqlite3.connect(SQLITE)
    try:
        tables = [r[0] for r in con.execute(
            "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")]
    finally:
        con.close()
    if not tables:
        raise RuntimeError("sqlite opened but has NO tables — DB not usable")
    return tables


def record(members, tables, ignored):
    entry = {
        "fix": os.path.basename(__file__),
        "target": os.path.relpath(SQLITE, SOURCE),
        "purpose": "install SQL DB at new checkouts/state (uncompress recipe, gitignore artifact)",
        "extracted": members,
        "tables": tables,
        "gitignored": ignored,
        "recorded": time.strftime("%Y-%m-%dT%H:%M:%S"),
    }
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)
    return entry


def main():
    members = extract()
    tables = verify()
    ignored = ensure_gitignore([SQLITE, os.path.join(STATE, "seed.php")])
    record(members, tables, ignored)
    print(json.dumps({"ok": True, "sqlite": os.path.relpath(SQLITE, MONO),
                      "size_bytes": os.path.getsize(SQLITE),
                      "tables": tables, "gitignored": ignored}, indent=2))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        tb = traceback.format_exc()
        ticket = {
            "filename": os.path.basename(__file__),
            "function": (traceback.extract_tb(exc.__traceback__)[-1].name
                         if exc.__traceback__ else "?"),
            "time-of-occurance": time.strftime("%Y-%m-%dT%H:%M:%S"),
            "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
            "possible-cause": "%s: %s" % (type(exc).__name__, exc),
            "traceback": tb.strip().splitlines()[-6:],
            "note": "ratchet link: install_state_db",
        }
        bugs = os.path.join(MONO, "file-system-repair", "bug_reports.jsonl")
        try:
            with open(bugs, "a") as fh:
                fh.write(json.dumps(ticket) + "\n")
        except OSError:
            pass
        print("EXCEPTION — ticket filed", file=sys.stderr)
        raise
