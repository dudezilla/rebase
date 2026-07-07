#!/usr/bin/env python3
"""api_key.py -- mint / list / revoke REST API keys for congruency.

A key authorizes REST writes and token-gated `?route=` endpoints (sent as `X-Api-Key: <key>` or `?key=`)
WITHOUT the admin login — the path services/scripts use. Keys live in the `api_keys` table of the CMS DB
(REST-denylisted, never exposed). Keys are SECRETS: mint per-deployment, print once, don't commit them.

    python3 tools/api_key.py --new <label> [--scope write]   # mint a key + print it once
    python3 tools/api_key.py --list                          # list labels/scopes (never the key values)
    python3 tools/api_key.py --revoke <key>                  # delete a key

Registry-gated (DB path from install.json's CONGRUENCY_SQLITE), python-only.
"""
import argparse
import json
import os
import secrets
import sqlite3
import sys
import time

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


def db_path():
    root = os.path.dirname(find_registry())
    cfg = os.path.join(root, "checkouts", "current", "install.json")
    try:
        j = json.load(open(cfg))
        if j.get("CONGRUENCY_SQLITE"):
            return os.path.expanduser(j["CONGRUENCY_SQLITE"])
    except Exception:  # noqa: BLE001
        pass
    return os.path.join(os.path.expanduser("~"), ".jazz", "congruency.sqlite")


def ensure(c):
    c.execute("CREATE TABLE IF NOT EXISTS api_keys (key TEXT PRIMARY KEY, label TEXT, scope TEXT, ts REAL)")


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    g = ap.add_mutually_exclusive_group(required=True)
    g.add_argument("--new", metavar="LABEL", help="mint a new key with this label")
    g.add_argument("--list", action="store_true", help="list keys (labels/scopes, not values)")
    g.add_argument("--revoke", metavar="KEY", help="delete a key by its value")
    ap.add_argument("--scope", default="write", help="scope label for --new (default: write)")
    a = ap.parse_args()

    c = sqlite3.connect(db_path(), timeout=8)
    try:
        ensure(c)
        if a.new:
            key = secrets.token_urlsafe(24)
            c.execute("INSERT INTO api_keys (key, label, scope, ts) VALUES (?,?,?,?)",
                      (key, a.new, a.scope, time.time()))
            c.commit()
            print("api_key: minted %r (scope=%s)" % (a.new, a.scope))
            print("  key: %s   (shown once -- save it; send as 'X-Api-Key: <key>')" % key)
        elif a.list:
            rows = c.execute("SELECT label, scope, ts FROM api_keys ORDER BY ts").fetchall()
            if not rows:
                print("api_key: no keys")
            for label, scope, ts in rows:
                print("  %-24s scope=%-8s minted=%s" % (label, scope, time.strftime("%Y-%m-%d", time.localtime(ts or 0))))
        elif a.revoke:
            n = c.execute("DELETE FROM api_keys WHERE key=?", (a.revoke,)).rowcount
            c.commit()
            print("api_key: revoked %d key(s)" % n)
        return 0
    finally:
        c.close()


if __name__ == "__main__":
    sys.exit(main())
