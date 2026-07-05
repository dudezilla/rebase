#!/usr/bin/env python3
"""release.py "<message>" — one-liner: commit everything + auto version bump.

Thin wrapper over the ops library: runs the `commit_release` operation (verify →
add -A → commit → python-computed version bump → smart tag → re-verify), with
bug-report-on-failure. Prefer:  python3 -m ops --op commit_release --args '{"message": "..."}'
"""
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))  # versioning/
import ops  # noqa: E402


def main(argv=None):
    argv = sys.argv[1:] if argv is None else argv
    if not argv or not argv[0].strip():
        print('usage: release.py "<commit message>"')
        return 2
    try:
        result = ops.run_op("commit_release", message=argv[0])
        print(json.dumps(result, default=str, indent=2))
        return 0
    except Exception as exc:  # noqa: BLE001
        print("FAILED — %s (bug report in %s)" % (exc, ops.BUG_LOG))
        return 1


if __name__ == "__main__":
    sys.exit(main())
