#!/usr/bin/env python3
"""Operation: regenerate the local git bundles from a source root's repos.

Rebuilds `<bundles_dir>/<name>.bundle` (all refs + tags) for each repo, verifies each
bundle re-opens, and — when `expect_tag` is given — asserts that tag is present in every
bundle. RAISES on any failure so the runner records a bug report. Instrumented via
gitutil. Output paths are ABSOLUTE (avoids the `git -C` relative-path bundle bug, BUG-V03).
"""
import os
import sys

VERSIONING = ("/home/notificationsforsteven/congruency/"
              "2a2e4f8c142eafaac061fe1cebc3c93cf27849b3/versioning")
sys.path.insert(0, VERSIONING)
from gitutil import Git, is_git_repo  # noqa: E402


class OperationError(Exception):
    pass


# (repo path relative to source_root, bundle basename) — matches existing ~/bundles names
DEFAULT_MAPPING = [
    ("congruency", "congruency"),
    ("congruency-harness", "congruency-harness"),
    ("congruency-tests", "congruency-tests"),
    ("congruency-bugs", "congruency-bugs"),
    ("coverage", "coverage"),
    ("pwdriver", "pwdriver"),
    ("congruency-bugs/vendor/congruency", "vendor-congruency"),
]


def run(source_root="/home/notificationsforsteven",
        bundles_dir="/home/notificationsforsteven/bundles",
        mapping=None,
        expect_tag=None,
        log=None):
    mapping = mapping or DEFAULT_MAPPING
    git = Git(logfile=log, echo=True)
    os.makedirs(bundles_dir, exist_ok=True)
    made = []
    for relpath, name in mapping:
        src = os.path.join(source_root, relpath)
        if not is_git_repo(src):
            raise OperationError("not a git repo: %s" % src)
        out = os.path.abspath(os.path.join(bundles_dir, name + ".bundle"))
        git.run(["bundle", "create", out, "--all"], src, write=False)  # raises on failure

        # `git bundle verify` reports validity via EXIT CODE (0 = ok) and prints its
        # message to stderr — do NOT parse stdout (BUG-V04). check=True raises GitError
        # on a bad bundle, which the runner turns into a bug report.
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
