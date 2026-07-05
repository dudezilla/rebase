#!/usr/bin/env python3
"""push_branch.py — push a branch from THIS checkout to another checkout (instrumented).

Propagates a change branch between working checkouts by PATH — no network, no bundle
round-trip. Adds the target repo as a path remote and pushes the branch, then verifies
the branch head is identical on both sides.

INSTRUMENTED: every git operation runs live and is logged via gitutil.Git; nothing from
memory. Pushing a NEW branch into a detached-HEAD target is conflict-free (the target has
no checked-out branch to refuse).

Usage:
  ./push_branch.py --target REPO --branch NAME [--source REPO]
                   [--remote-name NAME] [--name N --email E] [--log OUT.log]
"""
import argparse
import os
import sys

from gitutil import Git, GitError


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--target", required=True, help="destination repo (working checkout) path")
    ap.add_argument("--branch", required=True, help="branch to push")
    ap.add_argument("--source", default=None,
                    help="source repo (default: the repo containing this script)")
    ap.add_argument("--remote-name", default=None,
                    help="remote name to register in source (default: basename of target's parent)")
    ap.add_argument("--name", default="checkout3")
    ap.add_argument("--email", default="checkout3@congruency.local")
    ap.add_argument("--log", default=None)
    args = ap.parse_args(argv)

    identity = {"name": args.name, "email": args.email}
    git = Git(identity=identity, logfile=args.log, echo=True)

    source = args.source or os.path.dirname(os.path.abspath(__file__))
    src = git.query(["rev-parse", "--show-toplevel"], source)
    if not src:
        print("source is not a git repo: %s" % source)
        return 2
    target = os.path.abspath(args.target)
    tgt = git.query(["rev-parse", "--show-toplevel"], target)
    if not tgt:
        print("target is not a git repo: %s" % target)
        return 2

    remote = args.remote_name or ("to-" + os.path.basename(os.path.dirname(tgt)) or "target")
    branch = args.branch

    # register the target as a path remote in the source (idempotent)
    git.run(["remote", "remove", remote], src, write=False, check=False)
    git.run(["remote", "add", remote, tgt], src, write=False)

    # confirm the branch exists in source
    src_head = git.query(["rev-parse", "--verify", branch], src)
    if not src_head:
        print("branch %s not found in source %s" % (branch, src))
        return 2

    # push it (target is detached HEAD -> pushing a new branch is safe)
    git.run(["push", remote, "%s:%s" % (branch, branch)], src, write=False)

    # verify the branch head is identical on both sides
    tgt_head = git.query(["rev-parse", "--verify", branch], tgt)
    ok = bool(tgt_head) and tgt_head == src_head
    print("\n-- push result --")
    print("  branch      : %s" % branch)
    print("  source head : %s (%s)" % ((src_head or "?")[:10], src))
    print("  target head : %s (%s)" % ((tgt_head or "?")[:10], tgt))
    print("  %s" % ("OK — branch propagated, heads match" if ok
                    else "FAIL — heads differ or push did not land"))
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
