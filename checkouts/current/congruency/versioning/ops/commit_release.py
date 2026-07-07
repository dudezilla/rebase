#!/usr/bin/env python3
"""Operation: commit everything and cut a version release, in one shot.

Pipeline (all live + logged; RAISES on any deviation so the runner records a bug report):
  1. VERIFY integrity   — check every repo + submodule structurally (dirty allowed, since
                          we are about to commit); abort if any is invalid.
  2. STAGE everything   — `git add -A` in every module (respects .gitignore).
  3. no change → NO-OP  — if nothing is staged anywhere, do not commit and do not consume
                          a version number.
  4. INCREMENT version  — python-computed next checkout increment (major.NNNN) from live
                          `version-*` tags; nothing inferred by hand.
  5. COMMIT everything  — one commit per changed module; the primary repo's commit also
                          carries the project version manifest.
  6. SMART TAG          — the project version always advances, but a module is tagged ONLY
                          if its HEAD moved past its last version tag (frozen / unchanged
                          repos keep their previous tag).
  7. RE-VERIFY          — final integrity must be VALID (clean).
"""
import json
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(HERE))          # the versioning/ dir
from gitutil import Git, is_git_repo                          # noqa: E402
from verify_git_state import check_repo                       # noqa: E402
from version_source import compute_next_version, collect_modules, build_manifest  # noqa: E402
from paths import project_root, versioning_dir  # noqa: E402


class OperationError(Exception):
    pass


def latest_version(git, repo):
    """Return (tag, commit) of the highest version-* tag on repo, or (None, None)."""
    raw = git.query(["tag", "-l", "version-*"], repo) or ""
    best, best_key = None, None
    for line in raw.splitlines():
        t = line.strip()
        if not t.startswith("version-"):
            continue
        val = t[len("version-"):]
        try:
            major = int(val.split(".")[0])
            minor = int(val.split(".")[1]) if "." in val else 0
        except (ValueError, IndexError):
            continue
        key = (major, minor)
        if best_key is None or key > best_key:
            best_key, best = key, t
    if best is None:
        return None, None
    return best, git.query(["rev-list", "-n", "1", best], repo)


def run(root=None,
        primary_name="congruency",
        message=None,
        name="tournament-versioning",
        email="versioning@congruency.local",
        major=None,
        log=None):
    if not message:
        raise OperationError("a commit message is required")
    if root is None:
        root = project_root()                          # derived, not hard-coded (BUG-V05)
    git = Git(identity={"name": name, "email": email}, logfile=log, echo=False)

    subs, parents, standalone = collect_modules(git, root, include_generated=False)
    modules = subs + parents + standalone
    if not modules:
        raise OperationError("no modules discovered under %s" % root)
    primary = next((m for m in modules
                    if os.path.basename(m["name"]) == primary_name and not m["is_submodule"]), None)
    if not primary:
        raise OperationError("primary repo %r not found under %s" % (primary_name, root))

    # 1. verify integrity (structural; dirty allowed — we are about to commit)
    for m in modules:
        rr = check_repo(git, m["path"], allow_dirty=True)
        if not rr["valid"]:
            raise OperationError("integrity invalid in %s: %s" % (m["name"], rr["errors"]))

    # 2. stage everything, per module
    staged_modules = []
    for m in modules:
        git.run(["add", "-A"], m["path"], write=True)
        if (git.query(["diff", "--cached", "--name-only"], m["path"]) or "").strip():
            staged_modules.append(m)

    # 3. no change anywhere -> no-op, no version consumed
    if not staged_modules:
        return {"status": "no-op", "detail": "nothing to commit", "version_bumped": False}

    # 4. compute the next version from live tags (python-stamped)
    version = compute_next_version(git, primary["path"], major=major)
    tag = "version-%s" % version

    # 5. write the manifest into the primary repo + canonical root, then commit each module
    manifest = build_manifest(git, version, modules)
    committed = os.path.join(versioning_dir(), "project_versions.json")
    canonical = os.path.join(root, "project_versions.json")
    for p in (committed, canonical):
        with open(p, "w") as fh:
            json.dump(manifest, fh, indent=2)
    git.run(["add", "--", os.path.relpath(committed, primary["path"])], primary["path"], write=True)
    if primary not in staged_modules:
        staged_modules.append(primary)

    committed_in = []
    for m in staged_modules:
        before = git.query(["rev-parse", "HEAD"], m["path"])
        git.run(["commit", "-m", message], m["path"], write=True)
        after = git.query(["rev-parse", "HEAD"], m["path"])
        if after == before:
            raise OperationError("commit did not advance HEAD in %s" % m["name"])
        committed_in.append(m["name"])

    # 6. SMART TAG — only modules whose HEAD advanced past their last version tag
    tagged, skipped = [], []
    for m in modules:
        _, last_commit = latest_version(git, m["path"])
        head = git.query(["rev-parse", "HEAD"], m["path"])
        if last_commit is None or head != last_commit:
            git.run(["tag", "-a", tag, "-m", "Release %s of %s" % (version, m["name"])],
                    m["path"], write=True)
            tagged.append(m["name"])
        else:
            skipped.append(m["name"])

    # 7. refresh canonical manifest with resolved tag commits, then re-verify (clean)
    for e in manifest["modules"]:
        e["tagged"] = e["name"] in tagged
        e["tag_commit"] = git.query(["rev-list", "-n", "1", tag], e["path"]) if e["tagged"] else None
    with open(canonical, "w") as fh:
        json.dump(manifest, fh, indent=2)

    for m in modules:
        rr = check_repo(git, m["path"], allow_dirty=False)
        if not rr["valid"]:
            raise OperationError("post-release integrity invalid in %s: %s" % (m["name"], rr["errors"]))

    return {"version": version, "tag": tag, "message": message,
            "committed_in": committed_in, "tagged": tagged, "skipped": skipped}


if __name__ == "__main__":
    import argparse
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("-m", "--message", required=True)
    ap.add_argument("--root", default="/home/notificationsforsteven")
    ap.add_argument("--major", type=int, default=None)
    ap.add_argument("--log", default=None)
    a = ap.parse_args()
    print(json.dumps(run(root=a.root, message=a.message, major=a.major, log=a.log),
                     indent=2, default=str))
