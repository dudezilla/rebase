#!/usr/bin/env python3
"""db_verify.py -- integrity linter for the self-hosting archive.

Content-addressing check: every stored blob body must hash to its git blob id -- git_blob_sha(body) == hash.
This catches ingest fidelity loss (e.g. newline translation), DB corruption, or tampering. With --manifest it
also compares the RUNNING source (is_current refs) against git's HEAD tree -- a live manifest -- to catch
drift between what the DB says is running and what the repo actually has at HEAD.

    python3 tools/db_verify.py               # hash every blob body vs its stored git id
    python3 tools/db_verify.py --manifest    # also check is_current source == git ls-tree HEAD
    python3 tools/db_verify.py --db X         # verify a specific DB (e.g. a seed-built one)

Exit 0 if everything matches, 1 on any mismatch -- usable as a ratchet gate. Read-only, registry-gated.
"""
import argparse
import hashlib
import json
import os
import sqlite3
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))


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
    cfg = os.path.join(reg["__root__"], "checkouts", "current", "install.json")
    try:
        j = json.load(open(cfg))
        if j.get("CONGRUENCY_SQLITE"):
            return os.path.expanduser(j["CONGRUENCY_SQLITE"])
    except Exception:  # noqa: BLE001
        pass
    return os.path.join(os.path.expanduser("~"), ".jazz", "congruency.sqlite")


def git_blob_sha(text):
    """The git blob object id of a body: sha1('blob <bytelen>\\0' + utf-8 bytes)."""
    b = (text or "").encode("utf-8")
    return hashlib.sha1(b"blob %d\0" % len(b) + b).hexdigest()


def verify_blobs(dbfile):
    c = sqlite3.connect("file:%s?mode=ro" % dbfile, uri=True)
    total, bad = 0, []
    for tbl in ("code_blobs", "doc_blobs"):
        if not c.execute("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?", (tbl,)).fetchone():
            continue
        for h, body in c.execute('SELECT hash, body FROM "%s"' % tbl):
            total += 1
            if git_blob_sha(body) != h:
                bad.append((tbl, h))
    c.close()
    return total, bad


def head_manifest(root):
    r = subprocess.run(["git", "ls-tree", "-r", "HEAD"], cwd=root, capture_output=True, text=True)
    manifest = {}
    for line in r.stdout.splitlines():
        meta, _, path = line.partition("\t")
        parts = meta.split()
        if len(parts) == 3 and parts[1] == "blob":
            manifest[path] = parts[2]
    return manifest


def verify_manifest(dbfile, root):
    head = head_manifest(root)               # git ls-tree HEAD (independent truth when git is present)
    source = "git HEAD"
    if not head:                             # no git / not a repo (e.g. a production box) -> shipped manifest
        mpath = os.path.join(root, "checkouts", "current", "state", "manifest.json")
        if os.path.isfile(mpath):
            head = json.load(open(mpath))
            source = "state/manifest.json"
    c = sqlite3.connect("file:%s?mode=ro" % dbfile, uri=True)
    drift = []
    for tbl in ("code_refs", "doc_refs"):
        if not c.execute("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?", (tbl,)).fetchone():
            continue
        for path, h in c.execute('SELECT path, hash FROM "%s" WHERE is_current=1' % tbl):
            if path.startswith("main:"):        # deploy extras pulled from main, not HEAD
                continue
            if head.get(path) != h:
                drift.append((path, h, head.get(path)))
    c.close()
    return source, drift


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--db", help="verify a specific DB (default: the live CMS DB)")
    ap.add_argument("--manifest", action="store_true", help="also check is_current source vs git HEAD")
    a = ap.parse_args()

    reg = load_registry()
    dbfile = os.path.abspath(os.path.expanduser(a.db)) if a.db else db_path(reg)

    total, bad = verify_blobs(dbfile)
    print("db_verify: %d/%d blob bodies hash to their git id" % (total - len(bad), total))
    for tbl, h in bad[:10]:
        print("  MISMATCH  %s %s" % (tbl, h[:16]))
    if len(bad) > 10:
        print("  ... and %d more" % (len(bad) - 10))
    rc = 0 if not bad else 1

    if a.manifest:
        source, drift = verify_manifest(dbfile, reg["__root__"])
        print("db_verify: is_current source vs %s -> %s" % (
            source, "in sync" if not drift else "%d drifted" % len(drift)))
        for path, dbh, headh in drift[:10]:
            print("  DRIFT  %s  db=%s head=%s" % (path, (dbh or "-")[:12], (headh or "absent")[:12]))
        if drift:
            rc = 1

    return rc


if __name__ == "__main__":
    sys.exit(main())
