"""CLI entry for `python3 -m ops` — delegates to the shared ops.cli().

Prefer the standalone executable `opsrun.py` (it sets its own path). This module exists
so `python3 -m ops ...` also works when the versioning dir is importable.
"""
import sys

from . import cli

if __name__ == "__main__":
    sys.exit(cli())
