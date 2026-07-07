#!/usr/bin/env python3
"""Thin CLI shim over the ops package (single source of truth is ops/__init__.py).

Prefer:  PYTHONPATH=<versioning> python3 -m ops --op NAME --args '{...}'
Kept for compatibility:  python3 run_op.py NAME --args '{...}'
"""
import argparse
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))  # versioning/
import ops  # noqa: E402


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("op", help="op name (e.g. refresh_bundles)")
    ap.add_argument("--args", default="{}", help="JSON kwargs")
    ap.add_argument("--bugs", default=None)
    a = ap.parse_args(argv)
    try:
        result = ops.run_op(a.op, bugs=a.bugs, **json.loads(a.args))
        print("OK — operation succeeded")
        print("  result: %s" % json.dumps(result, default=str)[:800])
        return 0
    except Exception as exc:  # noqa: BLE001
        print("FAILED — %s (bug report in %s)" % (exc, ops.BUG_LOG))
        return 1


if __name__ == "__main__":
    sys.exit(main())
