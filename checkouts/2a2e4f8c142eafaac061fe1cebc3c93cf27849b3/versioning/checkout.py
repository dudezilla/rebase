#!/usr/bin/env python3
"""checkout.py — check the versioned project out into a specified folder.

Materialises every module (and its submodules) into a destination folder by cloning
from the LOCAL BUNDLES and checking out a version tag. Uses the project version
manifest to know what to lay down and how submodules are wired, then re-points each
submodule at its local bundle so the whole tree checks out offline.

INSTRUMENTED: every git operation runs live against the bundles and is logged via
gitutil.Git — nothing from memory.

Usage:
  ./checkout.py DEST [--version N] [--module NAME] [--bundles DIR]
                [--manifest FILE] [--force] [--log OUT.log]
"""
import argparse
import json
import os
import shutil
import sys

from gitutil import Git, parse_gitmodules


def bundle_for(name, bundles):
    return os.path.join(bundles, name.replace("/", "-") + ".bundle")


def checkout_module(git, m, dest_root, tag, bundles):
    name = m["name"]
    bundle = bundle_for(name, bundles)
    target = os.path.join(dest_root, os.path.basename(name))
    r = {"name": name, "target": target, "bundle": bundle, "ok": False, "errors": []}
    if not os.path.isfile(bundle):
        r["errors"].append("no bundle: %s" % bundle)
        return r

    git.run(["clone", "--quiet", bundle, target], dest_root, write=False)
    # detach at the version tag when present, else stay on the bundle's default branch
    tags = git.query(["tag", "-l", tag], target)
    if tags:
        git.run(["checkout", "--quiet", "tags/%s" % tag], target, write=False)
    r["ref"] = tag if tags else git.query(["rev-parse", "--abbrev-ref", "HEAD"], target)
    r["commit"] = git.query(["rev-parse", "HEAD"], target)

    # wire any submodules from their local bundles so they check out offline
    declared = parse_gitmodules(target, git)
    for sub_name, cfg in declared.items():
        subpath = cfg.get("path")
        if not subpath:
            continue
        sub_bundle = bundle_for(subpath, bundles)
        sub = {"subpath": subpath, "bundle": sub_bundle, "ok": False}
        if not os.path.isfile(sub_bundle):
            sub["error"] = "no submodule bundle: %s" % sub_bundle
            r.setdefault("submodules", []).append(sub)
            r["errors"].append(sub["error"])
            continue
        git.run(["submodule", "init", "--", subpath], target, write=False)
        # override the (unreachable) upstream url with the local bundle
        git.run(["config", "submodule.%s.url" % sub_name, sub_bundle], target, write=False)
        # allow the local file:// bundle as a submodule source (blocked by default
        # since CVE-2022-39253); scoped to this one command, not global config
        git.run(["-c", "protocol.file.allow=always",
                 "submodule", "update", "--", subpath], target, write=False)
        sub["commit"] = git.query(["rev-parse", "HEAD"], os.path.join(target, subpath))
        sub["ok"] = bool(sub["commit"])
        r.setdefault("submodules", []).append(sub)

    r["ok"] = not r["errors"]
    return r


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("dest", help="destination folder to check the project out into")
    ap.add_argument("--version", type=int, default=2)
    ap.add_argument("--module", default=None, help="only this module (default: all)")
    ap.add_argument("--bundles", default=os.path.expanduser("~/bundles"))
    ap.add_argument("--manifest", default=os.path.expanduser("~/project_versions.json"))
    ap.add_argument("--force", action="store_true", help="overwrite a non-empty dest")
    ap.add_argument("--log", default=None)
    args = ap.parse_args(argv)

    tag = "version-%d" % args.version
    if not os.path.isfile(args.manifest):
        print("manifest not found: %s (run version_source.py first)" % args.manifest)
        return 2
    manifest = json.load(open(args.manifest))

    dest = os.path.abspath(args.dest)
    if os.path.exists(dest) and os.listdir(dest):
        if not args.force:
            print("dest %s exists and is not empty (use --force)" % dest)
            return 2
        shutil.rmtree(dest)
    os.makedirs(dest, exist_ok=True)

    git = Git(logfile=args.log, echo=True)
    # top-level modules only; submodules are pulled in via their parent
    modules = [m for m in manifest["modules"] if not m.get("is_submodule")]
    if args.module:
        modules = [m for m in modules if m["name"] == args.module
                   or os.path.basename(m["name"]) == args.module]
        if not modules:
            print("no such module: %s" % args.module)
            return 2

    print("== checkout %s -> %s ==" % (tag, dest))
    results = [checkout_module(git, m, dest, tag, args.bundles) for m in modules]

    print("\n-- result --")
    ok = True
    for r in results:
        mark = "OK  " if r["ok"] else "FAIL"
        print("  [%s] %-20s %s @ %s" % (mark, os.path.basename(r["name"]),
                                        r.get("ref", "?"), (r.get("commit") or "?")[:10]))
        for s in r.get("submodules", []):
            print("        submodule %-20s @ %s" % (s["subpath"], (s.get("commit") or "?")[:10]))
        for e in r["errors"]:
            print("        - %s" % e)
            ok = False
    print("  --\n  %d module(s) into %s -> %s" % (len(results), dest, "OK" if ok else "INCOMPLETE"))
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
