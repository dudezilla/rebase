#!/usr/bin/env python3
"""Operation: verify each local bundle matches its origin repo.

For every repo: assert every `version-*` tag present in the repo is also present in its
bundle, and every branch head in the repo matches the branch head recorded in the bundle.
RAISES on any missing tag or branch drift so the runner records a bug report. Read-only
with respect to the repos (only reads git + bundle metadata).
"""
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(HERE))          # the versioning/ dir
from gitutil import Git, is_git_repo  # noqa: E402
from paths import project_root, bundles_dir as default_bundles_dir  # noqa: E402


class OperationError(Exception):
    pass


DEFAULT_MAPPING = [
    ("congruencey", "congruencey"),
    ("congruencey-harness", "congruencey-harness"),
    ("congruencey-tests", "congruencey-tests"),
    ("congruencey-bugs", "congruencey-bugs"),
    ("coverage", "coverage"),
    ("pwdriver", "pwdriver"),
    ("congruencey-bugs/vendor/congruencey", "vendor-congruencey"),
]


def _bundle_refs(git, repo, bundle):
    """Map ref-name -> object sha from `git bundle list-heads`."""
    raw = git.query(["bundle", "list-heads", bundle], repo) or ""
    refs = {}
    for line in raw.splitlines():
        parts = line.split(None, 1)
        if len(parts) == 2:
            refs[parts[1].strip()] = parts[0].strip()
    return refs


def run(root=None,
        bundles_dir=None,
        mapping=None,
        log=None):
    if root is None:
        root = project_root()                          # derived (BUG-V05)
    if bundles_dir is None:
        bundles_dir = default_bundles_dir()
    mapping = mapping or DEFAULT_MAPPING
    git = Git(logfile=log, echo=False)
    report = []
    for relpath, name in mapping:
        repo = os.path.join(root, relpath)
        bundle = os.path.join(bundles_dir, name + ".bundle")
        if not is_git_repo(repo):
            raise OperationError("not a git repo: %s" % repo)
        if not os.path.isfile(bundle):
            raise OperationError("missing bundle: %s" % bundle)

        refs = _bundle_refs(git, repo, bundle)

        # every version-* tag in the repo must be present in the bundle
        repo_tags = (git.query(["tag", "-l", "version-*"], repo) or "").split()
        missing = [t for t in repo_tags if ("refs/tags/%s" % t) not in refs]
        if missing:
            raise OperationError("bundle %s missing tags: %s" % (name, missing))

        # every branch head in the repo must match the bundle
        branch_lines = (git.query(
            ["for-each-ref", "--format=%(refname:short) %(objectname)", "refs/heads"],
            repo) or "").splitlines()
        drift = []
        for bl in branch_lines:
            bn, commit = bl.split()
            bh = refs.get("refs/heads/%s" % bn)
            if bh is None:
                drift.append("%s:absent" % bn)
            elif bh != commit:
                drift.append("%s:%s!=%s" % (bn, bh[:9], commit[:9]))
        if drift:
            raise OperationError("bundle %s branch drift: %s" % (name, drift))

        report.append({"bundle": name, "version_tags": len(repo_tags),
                       "branches": len(branch_lines)})

    return {"bundles_dir": bundles_dir, "count": len(report), "verified": report}


if __name__ == "__main__":
    import json
    print(json.dumps(run(), indent=2, default=str))
