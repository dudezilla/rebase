#!/usr/bin/env python3
"""db_import.py -- rebuild a CMS database from the SQL seed (state/seed.sql).

MANUAL tool -- NOT run on any crank or hook. Builds a fresh DB from the committed seed. Refuses to clobber
an existing target without --force, and never touches the live DB unless you point --to at it explicitly.

    python3 tools/db_import.py --verify                 # build to a throwaway temp DB + print table counts
    python3 tools/db_import.py --to /tmp/new.sqlite     # build a fresh DB from state/seed.sql[.xz]
    python3 tools/db_import.py --to X --force           # overwrite X if it exists

Registry-gated, python-only.
"""
import argparse
import json
import lzma
import os
import sqlite3
import sys
import tempfile
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = None


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


def bug_report(reg, exc, tb, note="db_import"):
    root = (reg or {}).get("__root__") or ROOT or HERE
    path = os.path.join(root, (reg or {}).get("bug_reports", "logs/bug_reports.jsonl"))
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 tools/db_import.py " + " ".join(sys.argv[1:]),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": note}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        open(path, "a").write(json.dumps(entry) + "\n")
    except OSError:
        pass
    return path


def seed_sql(reg):
    state = os.path.join(reg["__root__"], "checkouts", "current", "state")
    plain = os.path.join(state, "seed.sql")
    xz = plain + ".xz"
    # newline="" so CRLF in blob bodies survives the read (text mode would translate \r\n -> \n, breaking
    # the content hash — the seed is written with newline="" too).
    if os.path.isfile(plain):
        return open(plain, newline="").read()
    if os.path.isfile(xz):
        with lzma.open(xz, "rt", newline="") as f:
            return f.read()
    raise FileNotFoundError("no state/seed.sql[.xz] -- run tools/db_export.py first")


def build(sql, target):
    c = sqlite3.connect(target)
    c.executescript(sql)
    c.commit()
    counts = {t: c.execute('SELECT COUNT(*) FROM "%s"' % t).fetchone()[0]
              for (t,) in c.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")}
    c.close()
    return counts


def main():
    global ROOT
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--to", help="target DB path to build")
    ap.add_argument("--force", action="store_true", help="overwrite an existing --to target")
    ap.add_argument("--verify", action="store_true", help="build to a temp DB + print counts (no writes kept)")
    a = ap.parse_args()
    reg = None
    try:
        reg = load_registry()
        ROOT = reg["__root__"]
        sql = seed_sql(reg)

        if a.verify:
            fd, tmp = tempfile.mkstemp(suffix=".sqlite")
            os.close(fd)
            os.remove(tmp)
            counts = build(sql, tmp)
            os.remove(tmp)
            print("db_import --verify: seed builds cleanly -> %d rows across %d tables" % (
                sum(counts.values()), len(counts)))
            for t, n in counts.items():
                print("  %-24s %d" % (t, n))
            return 0

        if not a.to:
            sys.stderr.write("db_import: --to <path> required (or --verify)\n")
            return 2
        target = os.path.abspath(os.path.expanduser(a.to))
        if os.path.exists(target) and not a.force:
            sys.stderr.write("db_import: %s exists; pass --force to overwrite\n" % target)
            return 2
        if os.path.exists(target):
            os.remove(target)
        counts = build(sql, target)
        print("db_import: built %s (%d rows across %d tables)" % (
            target, sum(counts.values()), len(counts)))
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
