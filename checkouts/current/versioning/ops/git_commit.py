#!/usr/bin/env python3
"""Operation: stage paths and commit them into a repo, with verification.

Stages each path, asserts something is actually staged (unless expect_change=False),
commits with an EXPLICIT identity, and asserts HEAD advanced. RAISES on any deviation so
the runner records a bug report — a git write op that cannot silently no-op or drift.
"""
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(HERE))          # the versioning/ dir (has gitutil.py)
from gitutil import Git, is_git_repo  # noqa: E402


class OperationError(Exception):
    pass


def run(repo, paths, message,
        name="tournament-versioning",
        email="versioning@congruency.local",
        expect_change=True,
        log=None):
    if not is_git_repo(repo):
        raise OperationError("not a git repo: %s" % repo)
    if not paths:
        raise OperationError("no paths given to commit")

    git = Git(identity={"name": name, "email": email}, logfile=log, echo=True)
    for p in paths:
        git.run(["add", "--", p], repo, write=True)

    staged = (git.query(["diff", "--cached", "--name-only"], repo) or "").strip()
    if expect_change and not staged:
        raise OperationError("nothing staged to commit (paths matched no changes)")

    before = git.query(["rev-parse", "HEAD"], repo)
    git.run(["commit", "-m", message], repo, write=True)
    after = git.query(["rev-parse", "HEAD"], repo)
    if after == before:
        raise OperationError("commit did not advance HEAD (%s)" % before)

    return {"repo": repo, "commit": after, "branch":
            git.query(["rev-parse", "--abbrev-ref", "HEAD"], repo),
            "files": staged.splitlines()}


if __name__ == "__main__":
    import json
    print(json.dumps(run(sys.argv[1], sys.argv[2:-1], sys.argv[-1]), indent=2, default=str))
