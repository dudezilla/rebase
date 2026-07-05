"""paths.py — derive every project path RELATIVE to this file (no hard-coded absolutes).

The tools must work from a clone at any location. This module resolves the repo, the
project root, the entry-hash folder and the bundles dir from `__file__` + `git`, so ops
can default to these instead of hard-coding `/home/...` or the commit hash.

Fixes BUG-V05. (The larger redesign — all paths + args supplied via a python-loaded JSON
config — is tracked as RD-01.)
"""
import os
import subprocess

_HERE = os.path.dirname(os.path.abspath(__file__))          # the versioning/ dir


def versioning_dir():
    return _HERE


def entry_dir():
    """The commit-named entry folder (the parent of versioning/)."""
    return os.path.dirname(_HERE)


def entry_hash():
    return os.path.basename(entry_dir())


def repo_root():
    """Git toplevel of the repository the tools live in (works at any clone location)."""
    try:
        proc = subprocess.run(["git", "-C", _HERE, "rev-parse", "--show-toplevel"],
                              capture_output=True, text=True)
        if proc.returncode == 0 and proc.stdout.strip():
            return proc.stdout.strip()
    except Exception:
        pass
    return os.path.dirname(entry_dir())                     # entry -> repo (fallback)


def project_root():
    """Parent of the repo — where sibling repos and the bundles dir live."""
    return os.path.dirname(repo_root())


def bundles_dir():
    return os.path.join(project_root(), "bundles")
