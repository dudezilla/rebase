#!/usr/bin/env python3
"""ENTRY_POINT.py — the base-folder entry point for the congruency python tools.

Lives at the repository ROOT, OUTSIDE the build/entry tree. On a fresh clone the tools are
NOT installed and their package is not importable. This file bootstraps everything using
ONLY paths relative to itself (via __file__) — no absolute paths, no hard-coded commit
hash. It:

  1. discovers the commit-named entry folder (the one holding versioning/ops),
  2. puts that entry's `versioning/` dir on sys.path so the `ops` package imports without
     any installation,
  3. discovers and lists every runnable python tool, and runs ops/plans.

    python3 ENTRY_POINT.py                 # (or --list) discover & list every tool
    python3 ENTRY_POINT.py --op refresh_bundles --args '{"log": "/tmp/x.log"}'
    python3 ENTRY_POINT.py PLAN.json
"""
import glob
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))       # the repo root — our only anchor


def find_versioning():
    """Locate <entry>/versioning by discovery (relative to HERE; no hard-coded hash)."""
    hits = glob.glob(os.path.join(HERE, "*", "versioning", "ops", "__init__.py"))
    if hits:
        return os.path.dirname(os.path.dirname(hits[0]))            # .../<entry>/versioning
    direct = os.path.join(HERE, "versioning", "ops", "__init__.py")  # or beside us
    if os.path.isfile(direct):
        return os.path.join(HERE, "versioning")
    raise SystemExit("ENTRY_POINT: could not find */versioning/ops under %s" % HERE)


def bootstrap():
    versioning = find_versioning()
    if versioning not in sys.path:
        sys.path.insert(0, versioning)                  # make `ops`, gitutil, ... importable
    return versioning


def discover(versioning):
    """Return {tool_name: path-relative-to-repo-root} for every runnable python tool."""
    tools = {}
    for pattern in (os.path.join(versioning, "ops", "*.py"),
                    os.path.join(versioning, "*.py")):
        for path in sorted(glob.glob(pattern)):
            base = os.path.basename(path)
            if base.startswith("_"):                    # skip __init__.py / __main__.py
                continue
            tools.setdefault(os.path.splitext(base)[0], os.path.relpath(path, HERE))
    return tools


def main(argv=None):
    argv = list(sys.argv[1:] if argv is None else argv)
    versioning = bootstrap()

    if not argv or argv[0] in ("--list", "-l", "list"):
        tools = discover(versioning)
        print("congruency tools — %d found (paths relative to the repo root):\n" % len(tools))
        for name, rel in sorted(tools.items()):
            print("  %-22s ./%s" % (name, rel))
        print("\nrun a tool:")
        print("  python3 ENTRY_POINT.py --op NAME --args '{\"k\": \"v\"}'")
        print("  python3 ENTRY_POINT.py PLAN.json")
        return 0

    import ops                                           # importable now, via bootstrap()
    return ops.cli(argv)                                 # handles --op ... and PLAN.json


if __name__ == "__main__":
    sys.exit(main())
