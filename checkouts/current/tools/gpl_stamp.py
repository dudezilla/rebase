#!/usr/bin/env python3
"""gpl_stamp.py — ticket #29: keep the GPL short-notice header on every .php file.

--check (default) lists .php files missing the notice (exit non-zero if any).
--fix inserts the header right after the opening <?php of each missing file.

    python3 tools/gpl_stamp.py            # report missing
    python3 tools/gpl_stamp.py --fix      # stamp them
"""
import argparse, os, sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)                     # checkouts/current
MARK = "GNU GPLv2"
HEADER = ("/*\n"
          "Copyright (C) 2006 Steven Peterson\n"
          "Congruency is free software, licensed under the GNU GPLv2 or later.\n"
          "See the LICENSE file in the project root for full license terms.\n"
          "*/\n")


def php_files():
    for base, dirs, files in os.walk(ROOT):
        dirs[:] = [d for d in dirs if d not in (".git", "state", "vendor", "node_modules")]
        for fn in files:
            if fn.endswith(".php"):
                yield os.path.join(base, fn)


def stamp(path):
    # binary I/O so the file's existing line endings (CRLF/LF) are preserved exactly
    with open(path, "rb") as f:
        data = f.read()
    i = data.find(b"<?php")
    if i < 0:
        return False                              # not a standard php open; skip
    eol = b"\r\n" if b"\r\n" in data else b"\n"
    j = data.find(b"\n", i)
    if j < 0:
        j = i + 5
    lines = [b"/*",
             b"Copyright (C) 2006 Steven Peterson",
             b"Congruency is free software, licensed under the GNU GPLv2 or later.",
             b"See the LICENSE file in the project root for full license terms.",
             b"*/", b""]
    header = eol.join(lines)
    new = data[:j + 1] + header + data[j + 1:]
    with open(path, "wb") as f:
        f.write(new)
    return True


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--fix", action="store_true")
    a = ap.parse_args()
    missing = []
    for p in php_files():
        try:
            with open(p, "r", errors="replace") as f:
                if MARK not in f.read():
                    missing.append(p)
        except OSError:
            pass
    rel = [os.path.relpath(p, ROOT) for p in missing]
    print("%d .php files missing the GPL notice%s" % (len(missing), ":" if missing else " — all stamped ✓"))
    for r in sorted(rel):
        print("  " + r)
    if a.fix and missing:
        done = sum(1 for p in missing if stamp(p))
        print("\nstamped %d file(s)" % done)
        return 0
    return 1 if missing else 0


if __name__ == "__main__":
    sys.exit(main())
