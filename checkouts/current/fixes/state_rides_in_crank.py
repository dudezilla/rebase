#!/usr/bin/env python3
"""state_rides_in_crank.py — crank: document that state is carried IN the version commit.

The `state` side-branch and `state-*` tags are retired. A crank (commit + version tag) now
carries its own database as an in-tree checkouts/current/state/database.tar.xz, snapshotted
from the big unified db by make_state and captured by mint; install.py extracts it from the
version tree on checkout. This patch records that contract as state/README.md.

python-only, self-verifying, recorded.
"""
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))      # checkouts/current/fixes
SOURCE = os.path.dirname(HERE)                          # checkouts/current
STATE = os.path.join(SOURCE, "state")
README = os.path.join(STATE, "README.md")

CONTENT = """# state — carried in the crank

State is **not** a side branch. Each crank (a commit + `version-*` tag) carries its own
database as an in-tree artifact:

    checkouts/current/state/database.tar.xz

* **produced by** `tools/make_state.py` — snapshots the big unified database named by
  `STATE.json` `source_db` (default `~/.jazz/congruency.sqlite`) via sqlite's WAL-safe
  online backup, and writes the tarball here.
* **captured by** `tools/mint_crank.py` — runs make_state BEFORE commit, so `add -A`
  folds `database.tar.xz` into the version commit.
* **installed by** `install.py` — the version checkout materializes the tarball; `do_state`
  extracts it in place into `congruency.sqlite` (gitignored).

The retired `state` branch and `state-<version>` tags no longer exist.
"""


def main():
    os.makedirs(STATE, exist_ok=True)
    with open(README, "w") as fh:
        fh.write(CONTENT)
    got = open(README).read()
    assert "carried in the crank" in got and "database.tar.xz" in got, "state/README.md not written"
    print("wrote %s (%d bytes)" % (os.path.relpath(README, SOURCE), len(got)))
    return 0


if __name__ == "__main__":
    sys.exit(main())
