#!/usr/bin/env python3
"""opsrun.py — the executable entry point for the ops library.

Runs an ops PLAN (or a single op) through the bug-trap library, with EACH link passing
through the trap. Resolves its own path from __file__ — no PYTHONPATH, no `python -m`,
no shell wrapper. Run it directly:

    ./opsrun.py PLAN.json
    ./opsrun.py --op refresh_bundles --args '{"log": "/tmp/x.log"}'
"""
import os
import sys

# python reads its OWN path here — the versioning dir (parent of this ops/ package)
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import ops  # noqa: E402

if __name__ == "__main__":
    sys.exit(ops.cli())
