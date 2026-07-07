#!/usr/bin/env python3
"""Operation: propagate a version tag (and an optional branch) from a source root's
repos to their ORIGIN repos.

Pushes `refs/tags/<tag>` from every module in `source_root` to the matching repo under
`origin_root`, plus `refs/heads/<branch>` for the one module that carries the change.
Verifies each ref landed at the same commit; RAISES on any mismatch so the runner
records a bug report. Instrumented via gitutil (every git op logged, live).
"""
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(HERE))          # the versioning/ dir (has gitutil.py)
from gitutil import Git, is_git_repo  # noqa: E402
from paths import project_root  # noqa: E402


class OperationError(Exception):
    pass


DEFAULT_MODULES = [
    "congruency", "congruency-harness", "congruency-tests",
    "congruency-bugs", "coverage", "pwdriver",
    "congruency-bugs/vendor/congruency",
]


def run(source_root=None,
        origin_root=None,
        tag="version-2.0001",
        branch="from-checkout-3",
        branch_module="congruency",
        modules=None,
        log=None):
    if source_root is None:
        source_root = project_root()                   # derived (BUG-V05); override for cross-checkout
    if origin_root is None:
        origin_root = project_root()
    modules = modules or DEFAULT_MODULES
    git = Git(logfile=log, echo=True)
    propagated = []
    for m in modules:
        src = os.path.join(source_root, m)
        dst = os.path.join(origin_root, m)
        if not is_git_repo(src):
            raise OperationError("source is not a git repo: %s" % src)
        if not is_git_repo(dst):
            raise OperationError("origin is not a git repo: %s" % dst)

        src_tag_commit = git.query(["rev-list", "-n", "1", tag], src)
        if not src_tag_commit:
            raise OperationError("tag %s not present in source %s" % (tag, src))

        refspecs = ["refs/tags/%s:refs/tags/%s" % (tag, tag)]
        pushing_branch = bool(branch) and m == branch_module
        if pushing_branch:
            src_branch_commit = git.query(["rev-parse", "--verify", branch], src)
            if not src_branch_commit:
                raise OperationError("branch %s not present in source %s" % (branch, src))
            refspecs.insert(0, "refs/heads/%s:refs/heads/%s" % (branch, branch))

        git.run(["push", dst] + refspecs, src, write=False)   # raises on failure

        dst_tag_commit = git.query(["rev-list", "-n", "1", tag], dst)
        if dst_tag_commit != src_tag_commit:
            raise OperationError("tag %s did not propagate to %s (%s != %s)"
                                 % (tag, dst, dst_tag_commit, src_tag_commit))
        rec = {"module": m, "tag": tag, "tag_commit": dst_tag_commit}

        if pushing_branch:
            dst_branch_commit = git.query(["rev-parse", "--verify", branch], dst)
            if dst_branch_commit != src_branch_commit:
                raise OperationError("branch %s did not propagate to %s (%s != %s)"
                                     % (branch, dst, dst_branch_commit, src_branch_commit))
            rec["branch"] = branch
            rec["branch_commit"] = dst_branch_commit
        propagated.append(rec)

    return {"tag": tag, "source_root": source_root, "origin_root": origin_root,
            "count": len(propagated), "propagated": propagated}


if __name__ == "__main__":
    import json
    print(json.dumps(run(), indent=2, default=str))
