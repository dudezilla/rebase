#!/usr/bin/env python3
"""Thin CLI shim over the ops package (single source of truth is ops/__init__.py).

Prefer:  PYTHONPATH=<versioning> python3 -m ops PLAN.json
Kept for compatibility:  python3 run_ops.py PLAN.json
"""
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))  # versioning/
import ops  # noqa: E402


def main(argv=None):
    argv = sys.argv[1:] if argv is None else argv
    if not argv:
        print("usage: run_ops.py PLAN.json")
        return 2
    with open(argv[0]) as fh:
        steps = json.load(fh)
    try:
        results = ops.run_plan(steps)
        print("all %d step(s) OK" % len(results))
        for r in results:
            print("  - %s" % r["step"])
        return 0
    except ops.OpError as exc:
        print("FAILED: %s (bug report in %s)" % (exc, ops.BUG_LOG))
        return 1


if __name__ == "__main__":
    sys.exit(main())
