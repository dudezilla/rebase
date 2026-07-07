#!/usr/bin/env python3
"""db_export.py -- export the CMS database as a git-viewable SQL dump (+ compressed).

Dumps CONGRUENCY_SQLITE to state/seed.sql (readable CREATE TABLE + INSERT statements you can diff in git)
and state/seed.sql.xz (compressed, for a fast deploy restore). Excludes local-noise / secret tables by
default -- the `events` tracker log and the auth tables (Login_Password/User_Group_Mappings/Group_Privileges)
-- so the committed seed is a clean public showcase. The self-hosting source/doc archive (with full history)
is kept: the browsable version history is the point.

    python3 tools/db_export.py                 # -> state/seed.sql (+ .xz); events + auth excluded
    python3 tools/db_export.py --keep-auth     # keep the auth tables (bake in the demo admin), drop events
    python3 tools/db_export.py --keep-events   # include the tracker event log
    python3 tools/db_export.py --all           # include everything (auth + events)
    python3 tools/db_export.py --keep-auth --last-n 25   # keep only the last 25 commits of history

Registry-gated, python-only; Variant-A bug report on exception.
"""
import argparse
import json
import lzma
import os
import shutil
import sqlite3
import sys
import tempfile
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = None
DEFAULT_EXCLUDE = ("events", "Login_Password", "User_Group_Mappings", "Group_Privileges")


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
    p = find_registry()
    reg = json.load(open(p))
    reg["__root__"] = os.path.dirname(p)
    return reg


def db_path(reg):
    """The CMS DB the app reads: install.json's CONGRUENCY_SQLITE, else the jazz default."""
    cfg = os.path.join(reg["__root__"], "checkouts", "current", "install.json")
    try:
        j = json.load(open(cfg))
        if j.get("CONGRUENCY_SQLITE"):
            return os.path.expanduser(j["CONGRUENCY_SQLITE"])
    except Exception:  # noqa: BLE001
        pass
    return os.path.join(os.path.expanduser("~"), ".jazz", "congruency.sqlite")


def bug_report(reg, exc, tb, note="db_export"):
    root = (reg or {}).get("__root__") or ROOT or HERE
    path = os.path.join(root, (reg or {}).get("bug_reports", "logs/bug_reports.jsonl"))
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 tools/db_export.py " + " ".join(sys.argv[1:]),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": note}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        open(path, "a").write(json.dumps(entry) + "\n")
    except OSError:
        pass
    return path


def prune_history(c, last_n):
    """Cut the tail off the self-hosting archive: keep refs from the most recent N commits, plus ALWAYS the
    running source (is_current=1), then drop orphaned blobs. Bounds the history index without losing the
    current source; full history is rebuildable from git with `ingest_self --backfill`."""
    kept_commits = 0
    for refs, blobs in (("code_refs", "code_blobs"), ("doc_refs", "doc_blobs")):
        if not c.execute("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?", (refs,)).fetchone():
            continue
        keep = [r[0] for r in c.execute(
            'SELECT commit_sha FROM (SELECT commit_sha, MAX(ts) t FROM "%s" GROUP BY commit_sha) '
            'ORDER BY t DESC LIMIT ?' % refs, (last_n,))]
        kept_commits = max(kept_commits, len(keep))
        if not keep:
            continue
        ph = ",".join("?" * len(keep))
        c.execute('DELETE FROM "%s" WHERE is_current=0 AND commit_sha NOT IN (%s)' % (refs, ph), keep)
        c.execute('DELETE FROM "%s" WHERE hash NOT IN (SELECT hash FROM "%s")' % (blobs, refs))
    return kept_commits


def export(reg, exclude, last_n=None):
    db = db_path(reg)
    if not os.path.isfile(db):
        raise FileNotFoundError("no CMS DB at %s" % db)
    state = os.path.join(reg["__root__"], "checkouts", "current", "state")
    os.makedirs(state, exist_ok=True)

    # Work on a copy so the live DB is never mutated: drop excluded tables, prune history, VACUUM, then dump.
    fd, tmp = tempfile.mkstemp(suffix=".sqlite")
    os.close(fd)
    shutil.copy(db, tmp)
    try:
        c = sqlite3.connect(tmp)
        dropped = []
        for t in exclude:
            if c.execute("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?", (t,)).fetchone():
                c.execute('DROP TABLE "%s"' % t)
                dropped.append(t)
        if last_n:
            prune_history(c, last_n)
        c.commit()
        c.execute("VACUUM")
        c.commit()
        sql = "\n".join(c.iterdump()) + "\n"
        c.close()
    finally:
        os.remove(tmp)

    seed = os.path.join(state, "seed.sql")
    # newline="" so CRLF in blob bodies survives the write (text mode would translate \r\n -> \n and break
    # the content hash on round-trip; db_import reads with newline="" too).
    with open(seed, "w", newline="") as f:
        f.write(sql)
    with lzma.open(seed + ".xz", "wt", preset=9, newline="") as f:
        f.write(sql)
    return seed, len(sql.encode("utf-8")), dropped


def main():
    global ROOT
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--keep-events", action="store_true", help="include the tracker event log")
    ap.add_argument("--keep-auth", action="store_true", help="include the auth tables (bakes in the demo admin)")
    ap.add_argument("--all", action="store_true", help="include everything (auth + events)")
    ap.add_argument("--last-n", type=int, metavar="N",
                    help="keep only the last N commits of source/doc history (+ always the running source)")
    a = ap.parse_args()
    reg = None
    try:
        reg = load_registry()
        ROOT = reg["__root__"]
        auth = ("Login_Password", "User_Group_Mappings", "Group_Privileges")
        if a.all:
            exclude = ()
        else:
            exclude = tuple(t for t in DEFAULT_EXCLUDE
                            if not (a.keep_events and t == "events")
                            and not (a.keep_auth and t in auth))
        seed, nbytes, dropped = export(reg, exclude, a.last_n)
        rel = os.path.relpath(seed, reg["__root__"])
        hist = ("last %d commits" % a.last_n) if a.last_n else "full history"
        print("db_export: wrote %s (%.2f MB text, %.2f MB xz); history: %s; excluded: %s" % (
            rel, nbytes / 1024 / 1024, os.path.getsize(seed + ".xz") / 1024 / 1024,
            hist, ", ".join(dropped) or "none"))
        return 0
    except Exception as e:  # noqa: BLE001
        tb = traceback.format_exc()
        try:
            bug_report(reg, e, tb)
        except Exception:  # noqa: BLE001
            pass
        sys.stderr.write(tb)
        return 1


if __name__ == "__main__":
    sys.exit(main())
