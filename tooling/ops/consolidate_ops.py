#!/usr/bin/env python3
"""Operation: consolidate the ops commit(s) from a source branch onto a target line.

Moves every commit on `source_branch` since its merge-base with `onto_branch` (expected
to touch only `versioning/ops` and `versioning/logs`) onto `onto_branch` via cherry-pick,
then resets `source_branch` back to the merge-base so the work lives on ONE line.

Safety: refuses to move a commit that touches anything outside the allowed prefixes;
verifies the ops land on the target and are gone from the source. RAISES on any deviation
so the runner records a bug report. Instrumented via gitutil.
"""
import os
import sys

VERSIONING = ("/home/notificationsforsteven/congruency/"
              "2a2e4f8c142eafaac061fe1cebc3c93cf27849b3/versioning")
sys.path.insert(0, VERSIONING)
from gitutil import Git, is_git_repo  # noqa: E402


class OperationError(Exception):
    pass


ALLOWED = ("2a2e4f8c142eafaac061fe1cebc3c93cf27849b3/versioning/ops",
           "2a2e4f8c142eafaac061fe1cebc3c93cf27849b3/versioning/logs")
SENTINEL = "2a2e4f8c142eafaac061fe1cebc3c93cf27849b3/versioning/ops/run_op.py"


def run(repo="/home/notificationsforsteven/congruency",
        onto_branch="from-checkout-3",
        source_branch="order-logging",
        name="tournament-versioning",
        email="versioning@congruency.local",
        log=None):
    if not is_git_repo(repo):
        raise OperationError("not a git repo: %s" % repo)
    git = Git(identity={"name": name, "email": email}, logfile=log, echo=True)

    mb = git.query(["merge-base", source_branch, onto_branch], repo)
    if not mb:
        raise OperationError("no merge-base between %s and %s" % (source_branch, onto_branch))
    commits = (git.query(["rev-list", "--reverse", "%s..%s" % (mb, source_branch)], repo) or "").split()
    if not commits:
        raise OperationError("no commits on %s since merge-base to consolidate" % source_branch)

    # safety: every commit must touch only the allowed prefixes
    for c in commits:
        files = (git.query(["show", "--stat", "--format=", "--name-only", c], repo) or "").splitlines()
        bad = [f for f in files if f.strip() and not any(f.startswith(a) for a in ALLOWED)]
        if bad:
            raise OperationError("commit %s touches files outside ops/logs: %s" % (c[:9], bad[:3]))

    # cherry-pick onto the target line
    git.run(["checkout", onto_branch], repo, write=True)
    before = git.query(["rev-parse", "HEAD"], repo)
    for c in commits:
        git.run(["cherry-pick", c], repo, write=True)
    after = git.query(["rev-parse", "HEAD"], repo)
    if after == before:
        raise OperationError("cherry-pick did not advance %s" % onto_branch)
    onto_tree = git.query(["ls-tree", "-r", "--name-only", "HEAD"], repo) or ""
    if SENTINEL not in onto_tree:
        raise OperationError("ops not present on %s after cherry-pick" % onto_branch)

    # reset the source branch back to the merge-base (pointer move; not checked out)
    git.run(["branch", "-f", source_branch, mb], repo, write=True)
    src_tree = git.query(["ls-tree", "-r", "--name-only", source_branch], repo) or ""
    if SENTINEL in src_tree:
        raise OperationError("ops still present on %s after reset" % source_branch)

    return {"repo": repo, "onto_branch": onto_branch, "onto_head": after,
            "source_branch": source_branch, "source_reset_to": mb,
            "moved_commits": commits}


if __name__ == "__main__":
    import json
    print(json.dumps(run(), indent=2, default=str))
