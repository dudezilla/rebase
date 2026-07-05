#!/usr/bin/env python3
"""verify_git_state.py — verify the multi-repo project git state is VALID.

Checks every repository under the project root and, for each, every submodule —
ensuring submodules are instrumented correctly so the repositories cannot drift
out of sync (no pointer mismatches, no uninitialised or dirty submodules, a
consistent .gitmodules). Exits 0 only when the whole project is valid.

INSTRUMENTED: every git call is executed live and logged (see gitutil.Git); no
state is taken from memory. Emits a JSON report and a human summary.

Usage:
  ./verify_git_state.py [--root DIR] [--json OUT.json] [--log OUT.log]
                        [--allow-dirty] [--include-generated] [--quiet]
"""
import argparse
import json
import os
import sys

from gitutil import (Git, discover_top_repos, is_git_repo, parse_gitmodules,
                     submodule_status)

FLAG_MEANING = {" ": "in-sync", "+": "POINTER-MISMATCH", "-": "UNINITIALISED",
                "U": "MERGE-CONFLICT"}


def check_repo(git, path, allow_dirty):
    r = {"path": path, "name": os.path.basename(path), "checks": {}, "submodules": [],
         "errors": [], "warnings": []}
    ck = r["checks"]

    # 1. valid repo + resolvable HEAD
    top = git.query(["rev-parse", "--show-toplevel"], path)
    ck["is_repo"] = bool(top)
    if not top:
        r["errors"].append("not a git repository")
        return r
    head = git.query(["rev-parse", "HEAD"], path)
    ck["head"] = head
    if not head:
        r["errors"].append("HEAD does not resolve (unborn/detached-broken)")
    branch = git.query(["rev-parse", "--abbrev-ref", "HEAD"], path)
    ck["branch"] = branch

    # 2. clean working tree
    porcelain = git.query(["status", "--porcelain"], path) or ""
    dirty = [l for l in porcelain.splitlines() if l.strip()]
    ck["clean"] = not dirty
    ck["dirty_count"] = len(dirty)
    if dirty:
        (r["warnings"] if allow_dirty else r["errors"]).append(
            "working tree dirty (%d entries)" % len(dirty))

    # 3. filemode config (mode-only noise is a known hazard here)
    ck["core_filemode"] = git.query(["config", "--get", "core.fileMode"], path)

    # 4. submodules: correct instrumentation + no cross-repo mismatch
    declared = parse_gitmodules(path, git)
    statuses = submodule_status(path, git)
    ck["submodule_count"] = len(statuses)
    seen_paths = set()
    for st in statuses:
        sp = st["subpath"]
        seen_paths.add(sp)
        sub = {"subpath": sp, "flag": st["flag"], "state": FLAG_MEANING.get(st["flag"], "?"),
               "recorded_sha": st["sha"], "errors": []}
        subabs = os.path.join(path, sp)

        # (a) declared in .gitmodules with a url
        decl = next((v for v in declared.values() if v.get("path") == sp), None)
        sub["declared"] = bool(decl)
        sub["url"] = (decl or {}).get("url")
        if not decl:
            sub["errors"].append(".gitmodules has no entry for this path")
        elif not decl.get("url"):
            sub["errors"].append(".gitmodules entry missing url")

        # (b) initialised: url wired into the parent's config
        cfg_url = git.query(["config", "--get",
                             "submodule.%s.url" % _module_name(declared, sp)], path)
        sub["initialised"] = bool(cfg_url)
        if st["flag"] == "-":
            sub["errors"].append("submodule not initialised (run: git submodule update --init)")

        # (c) NO POINTER MISMATCH: parent gitlink == submodule HEAD
        gitlink = _gitlink_sha(git, path, sp)
        sub["parent_gitlink"] = gitlink
        if is_git_repo(subabs):
            sub_head = git.query(["rev-parse", "HEAD"], subabs)
            sub["submodule_head"] = sub_head
            if gitlink and sub_head and gitlink != sub_head:
                sub["errors"].append("MISMATCH: parent records %s but submodule HEAD is %s"
                                     % (gitlink[:10], sub_head[:10]))
            # (d) submodule working tree clean
            sub_porc = git.query(["status", "--porcelain"], subabs) or ""
            sub_dirty = [l for l in sub_porc.splitlines() if l.strip()]
            sub["clean"] = not sub_dirty
            if sub_dirty:
                (r["warnings"] if allow_dirty else sub["errors"]).append(
                    "submodule working tree dirty (%d)" % len(sub_dirty))
        if st["flag"] == "+":
            sub["errors"].append("checked-out commit differs from recorded pointer (+)")
        if st["flag"] == "U":
            sub["errors"].append("submodule has merge conflicts (U)")

        r["submodules"].append(sub)
        r["errors"].extend("%s: %s" % (sp, e) for e in sub["errors"])

    # declared-but-absent
    for name, v in declared.items():
        if v.get("path") and v["path"] not in seen_paths:
            r["errors"].append("%s declared in .gitmodules but not present" % v["path"])

    r["valid"] = not r["errors"]
    return r


def _module_name(declared, subpath):
    for name, v in declared.items():
        if v.get("path") == subpath:
            return name
    return subpath


def _gitlink_sha(git, path, subpath):
    out = git.query(["ls-tree", "HEAD", subpath], path)
    # format: "160000 commit <sha>\t<path>"
    if out and "commit" in out:
        try:
            return out.split()[2]
        except IndexError:
            return None
    return None


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--root", default=os.path.expanduser("~"))
    ap.add_argument("--json", default=None, help="write JSON report here")
    ap.add_argument("--log", default=None, help="instrumentation log path")
    ap.add_argument("--allow-dirty", action="store_true",
                    help="treat dirty trees as warnings, not failures")
    ap.add_argument("--include-generated", action="store_true")
    ap.add_argument("--quiet", action="store_true")
    args = ap.parse_args(argv)

    git = Git(logfile=args.log, echo=not args.quiet)
    repos = discover_top_repos(args.root, git, include_generated=args.include_generated)

    report = {"root": args.root, "repos": [], "valid": True,
              "total_repos": 0, "total_submodules": 0}
    for path in repos:
        rr = check_repo(git, path, args.allow_dirty)
        report["repos"].append(rr)
        report["total_repos"] += 1
        report["total_submodules"] += len(rr["submodules"])
        if not rr.get("valid"):
            report["valid"] = False

    # ---- human summary ----
    print("\n== git state verification ==")
    for rr in report["repos"]:
        mark = "OK  " if rr.get("valid") else "FAIL"
        subinfo = ""
        if rr["submodules"]:
            subinfo = "  submodules: " + ", ".join(
                "%s[%s]" % (s["subpath"], s["state"]) for s in rr["submodules"])
        print("  [%s] %-22s %s%s" % (
            mark, rr["name"],
            (rr["checks"].get("branch") or "-"),
            subinfo))
        for e in rr["errors"]:
            print("         - ERROR: %s" % e)
        for w in rr["warnings"]:
            print("         - warn: %s" % w)
    print("  --")
    print("  %d repos, %d submodules checked -> %s"
          % (report["total_repos"], report["total_submodules"],
             "VALID" if report["valid"] else "INVALID"))

    if args.json:
        with open(args.json, "w") as fh:
            json.dump(report, fh, indent=2)
        print("  json report: %s" % args.json)

    return 0 if report["valid"] else 1


if __name__ == "__main__":
    sys.exit(main())
