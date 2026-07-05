#!/usr/bin/env python3
"""Operation: regenerate the local git bundles from a source root's repos.

Rebuilds `<bundles_dir>/<name>.bundle` (all refs + tags) for each repo, verifies each
bundle re-opens (by EXIT CODE — BUG-V04), and — when `expect_tag` is given — asserts
that tag is present in every bundle. RAISES on any failure so the runner records a bug
report. Instrumented via gitutil. Output paths are ABSOLUTE (avoids BUG-V03).
"""
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(HERE))          # the versioning/ dir (has gitutil.py)
from gitutil import Git, is_git_repo  # noqa: E402
from paths import project_root, bundles_dir as default_bundles_dir  # noqa: E402


class OperationError(Exception):
    pass


# (repo path relative to source_root, bundle basename) — matches existing ~/bundles names
DEFAULT_MAPPING = [
    ("congruencey", "congruencey"),
    ("congruencey-harness", "congruencey-harness"),
    ("congruencey-tests", "congruencey-tests"),
    ("congruencey-bugs", "congruencey-bugs"),
    ("coverage", "coverage"),
    ("pwdriver", "pwdriver"),
    ("congruencey-bugs/vendor/congruencey", "vendor-congruencey"),
]


def run(source_root=None,
        bundles_dir=None,
        mapping=None,
        expect_tag=None,
        log=None):
    if source_root is None:
        source_root = project_root()                   # derived (BUG-V05)
    if bundles_dir is None:
        bundles_dir = default_bundles_dir()
    mapping = mapping or DEFAULT_MAPPING
    git = Git(logfile=log, echo=False)
    os.makedirs(bundles_dir, exist_ok=True)
    made = []
    for relpath, name in mapping:
        src = os.path.join(source_root, relpath)
        if not is_git_repo(src):
            raise OperationError("not a git repo: %s" % src)
        out = os.path.abspath(os.path.join(bundles_dir, name + ".bundle"))
        git.run(["bundle", "create", out, "--all"], src, write=False)  # raises on failure

        # `git bundle verify` reports validity via EXIT CODE (0 = ok) and prints to
        # stderr — rely on the exit code, not stdout text (BUG-V04). check=True raises.
        git.run(["bundle", "verify", out], src, write=False, check=True)
        if expect_tag:
            heads = git.query(["bundle", "list-heads", out], src) or ""
            if ("refs/tags/%s" % expect_tag) not in heads:
                raise OperationError("bundle %s is missing expected tag %s" % (out, expect_tag))
        made.append({"repo": relpath, "bundle": out})

    return {"bundles_dir": bundles_dir, "expect_tag": expect_tag,
            "count": len(made), "bundles": made}


if __name__ == "__main__":
    import json
    print(json.dumps(run(), indent=2, default=str))
