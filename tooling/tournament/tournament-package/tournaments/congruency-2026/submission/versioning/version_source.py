#!/usr/bin/env python3
"""version_source.py — introduce initial versioning across the whole project.

A previously-missing feature: version ALL original source code as version 2. This
script, with an EXPLICIT git identity (instrumentation — never ambient config):

  1. verifies the project git state first (aborts if invalid, unless --force);
  2. ensures every module and every submodule BOUNDARY carries an appropriate
     README;
  3. writes a project-wide version MANIFEST;
  4. reconciles any submodule pointers it moved;
  5. tags every repository AND every submodule with an annotated version tag
     (default: version-2).

It may make multiple commits. Every git operation is executed live and logged —
nothing is done from memory. Run verify_git_state.py again afterwards to confirm.

Usage:
  ./version_source.py [--version N] [--name NAME --email EMAIL]
                      [--root DIR] [--log OUT.log] [--force] [--dry-run]
"""
import argparse
import json
import os
import sys
import time

from gitutil import (Git, discover_top_repos, is_git_repo, parse_gitmodules,
                     submodule_status)
from verify_git_state import check_repo

DESC = {
    "congruency": ("Congruency CMS — the tournament SOURCE repository. The selected "
                    "winning submission lives under the entry-hash folder: the evolved "
                    "tag engine, its self-configuring oracle, and the zero-JS showcase."),
    "congruency-harness": ("Boot & serve harness for the Congruency CMS: a static PHP "
                            "runtime, the database seeder, and the dev-server router. "
                            "`./serve` starts the winning showcase (the default home); "
                            "`./serve --legacy` starts the original CMS."),
    "congruency-tests": ("Verification apparatus (the oracle) for the tournament: the "
                          "test suites and the end-to-end verify harness."),
    "congruency-bugs": ("Catalogue of 15 defects in the original Congruency CMS, each "
                         "with a runnable reproduction. Embeds the pristine original "
                         "source as a git submodule under `vendor/congruency`."),
    "coverage": ("Tokenizer-based PHP branch-coverage tool used to measure test reach "
                 "across the tournament code."),
    "pwdriver": ("Playwright-based adversarial attack loop that probes the running CMS "
                 "for defects."),
}


def module_readme(name, version):
    body = DESC.get(name, "%s — a Congruency project module." % name)
    return ("# %s\n\n%s\n\n---\n_Versioned as **version-%s** by the project versioning "
            "apparatus._\n" % (name, body, version))


def boundary_readme(subpath, url, version):
    return ("# %s — submodule boundary\n\n"
            "`%s` is a **git submodule**: the pristine original Congruency source "
            "(the 2006 \"old-code\"), pinned by commit and intentionally frozen — do "
            "not edit it in place; move the pointer via the submodule instead.\n\n"
            "* upstream: `%s`\n"
            "* wiring:   see the parent repository's `.gitmodules`\n\n"
            "---\n_Boundary documented for **version-%s**._\n"
            % (os.path.dirname(subpath) or subpath, subpath, url or "(see .gitmodules)", version))


class Versioner:
    def __init__(self, git, version, dry_run):
        self.git = git
        self.version = version
        self.tag = "version-%s" % version
        self.dry = dry_run
        self.commits = []
        self.tags = []

    def _write_and_commit(self, repo, relpath, content, msg):
        abspath = os.path.join(repo, relpath)
        if os.path.exists(abspath):
            return False
        print("  + create %s/%s" % (os.path.basename(repo), relpath))
        if self.dry:
            return True
        os.makedirs(os.path.dirname(abspath) or repo, exist_ok=True)
        with open(abspath, "w") as fh:
            fh.write(content)
        self.git.run(["add", "--", relpath], repo, write=True)
        self.git.run(["commit", "-m", msg], repo, write=True)
        self.commits.append((repo, msg))
        return True

    def ensure_module_readme(self, repo):
        name = os.path.basename(repo)
        return self._write_and_commit(
            repo, "README.md", module_readme(name, self.version),
            "docs: add module README (version-%s boundary)" % self.version)

    def ensure_boundary_readme(self, parent, subpath, url):
        bdir = os.path.dirname(subpath) or subpath
        rel = os.path.join(bdir, "README.md")
        return self._write_and_commit(
            parent, rel, boundary_readme(subpath, url, self.version),
            "docs: document submodule boundary %s (version-%s)" % (bdir, self.version))

    def reconcile_pointer(self, parent, subpath):
        """If the submodule HEAD moved, record the new pointer in the parent."""
        stt = submodule_status(parent, self.git)
        moved = any(s["subpath"] == subpath and s["flag"] == "+" for s in stt)
        if not moved:
            return False
        print("  ~ reconcile submodule pointer %s in %s" % (subpath, os.path.basename(parent)))
        if self.dry:
            return True
        self.git.run(["add", "--", subpath], parent, write=True)
        self.git.run(["commit", "-m",
                      "chore: update %s pointer for version-%s" % (subpath, self.version)],
                     parent, write=True)
        self.commits.append((parent, "reconcile %s" % subpath))
        return True

    def tag_module(self, repo, force):
        name = os.path.basename(repo)
        existing = self.git.query(["tag", "-l", self.tag], repo)
        if existing and not force:
            print("  ! tag %s already exists in %s (use --force to move)" % (self.tag, name))
            self.tags.append((repo, self.tag, "exists"))
            return
        head = self.git.query(["rev-parse", "HEAD"], repo)
        print("  * tag %s -> %s @ %s" % (self.tag, name, (head or "?")[:10]))
        if self.dry:
            self.tags.append((repo, self.tag, "dry"))
            return
        args = ["tag", "-a"]
        if force:
            args.append("-f")
        args += [self.tag, "-m",
                 "Version %s — initial versioning of %s" % (self.version, name)]
        self.git.run(args, repo, write=True)
        self.tags.append((repo, self.tag, head))


def build_manifest(git, version, modules):
    man = {
        "project": "congruency-tournament",
        "version": version,
        "tag": "version-%s" % version,
        "generated": time.strftime("%Y-%m-%dT%H:%M:%S"),
        "note": "Initial project-wide versioning. Every module and submodule is tagged "
                "version-%s; see each repo's tag for the exact commit." % version,
        "modules": [],
    }
    for m in modules:
        entry = {
            "name": m["name"],
            "path": m["path"],
            "is_submodule": m["is_submodule"],
            "parent": m.get("parent"),
            "url": m.get("url"),
            "branch": git.query(["rev-parse", "--abbrev-ref", "HEAD"], m["path"]),
            "commit": git.query(["rev-parse", "HEAD"], m["path"]),
            "tag": "version-%s" % version,
        }
        man["modules"].append(entry)
    return man


def collect_modules(git, root, include_generated):
    """Ordered list: submodules first (leaves), then their parents, then standalones."""
    tops = discover_top_repos(root, git, include_generated=include_generated)
    subs, parents, standalone = [], [], []
    for path in tops:
        declared = parse_gitmodules(path, git)
        stt = submodule_status(path, git)
        if stt:
            for s in stt:
                sp = s["subpath"]
                decl = next((v for v in declared.values() if v.get("path") == sp), {})
                subs.append({"name": sp, "path": os.path.join(path, sp),
                             "is_submodule": True, "parent": os.path.basename(path),
                             "parent_path": path, "subpath": sp,
                             "url": decl.get("url")})
            parents.append({"name": os.path.basename(path), "path": path,
                            "is_submodule": False, "parent": None,
                            "submodules": [{"subpath": s["subpath"],
                                            "url": next((v for v in declared.values()
                                                         if v.get("path") == s["subpath"]), {}).get("url")}
                                           for s in stt]})
        else:
            standalone.append({"name": os.path.basename(path), "path": path,
                               "is_submodule": False, "parent": None, "submodules": []})
    return subs, parents, standalone


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--version", type=int, default=2)
    ap.add_argument("--name", default="tournament-versioning")
    ap.add_argument("--email", default="versioning@congruency.local")
    ap.add_argument("--root", default=os.path.expanduser("~"))
    ap.add_argument("--log", default=None)
    ap.add_argument("--force", action="store_true",
                    help="proceed even if verify fails; move existing tags")
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args(argv)

    identity = {"name": args.name, "email": args.email}
    git = Git(identity=identity, logfile=args.log, echo=True)
    print("== versioning: identity=%s <%s>  version=%d  dry_run=%s =="
          % (identity["name"], identity["email"], args.version, args.dry_run))

    # -- 1. verify first --------------------------------------------------------
    print("\n-- pre-flight verification --")
    tops = discover_top_repos(args.root, git, include_generated=False)
    invalid = []
    for p in tops:
        rr = check_repo(git, p, allow_dirty=False)
        if not rr.get("valid"):
            invalid.append((rr["name"], rr["errors"]))
    if invalid:
        print("  invalid state in: %s" % ", ".join(n for n, _ in invalid))
        for n, errs in invalid:
            for e in errs:
                print("    %s: %s" % (n, e))
        if not args.force:
            print("ABORT — fix the above or pass --force.")
            return 2
        print("  --force: continuing despite invalid state.")
    else:
        print("  state valid.")

    subs, parents, standalone = collect_modules(git, args.root, include_generated=False)
    v = Versioner(git, args.version, args.dry_run)

    # -- 2. READMEs: submodules (own), boundaries, parents, standalones ---------
    print("\n-- READMEs & boundaries --")
    for m in subs:
        v.ensure_module_readme(m["path"])                 # skips if present (keeps pins pristine)
    for p in parents:
        for sm in p["submodules"]:
            v.ensure_boundary_readme(p["path"], sm["subpath"], sm.get("url"))
            v.reconcile_pointer(p["path"], sm["subpath"])
        v.ensure_module_readme(p["path"])
    for m in standalone:
        v.ensure_module_readme(m["path"])

    all_modules = subs + parents + standalone

    # -- 3. project-wide manifest ----------------------------------------------
    print("\n-- manifest --")
    manifest = build_manifest(git, args.version, all_modules)
    script_dir = os.path.dirname(os.path.abspath(__file__))
    committed_manifest = os.path.join(script_dir, "project_versions.json")
    canonical_manifest = os.path.join(args.root, "project_versions.json")
    if not args.dry_run:
        for path in (committed_manifest, canonical_manifest):
            with open(path, "w") as fh:
                json.dump(manifest, fh, indent=2)
        # commit the versioning dir (scripts + manifest) into its own repo
        host_repo = git.query(["rev-parse", "--show-toplevel"], script_dir)
        rel = os.path.relpath(script_dir, host_repo)
        git.run(["add", "--", rel], host_repo, write=True)
        # only commit if something is staged
        staged = git.query(["diff", "--cached", "--name-only"], host_repo)
        if staged:
            git.run(["commit", "-m",
                     "feat(versioning): project-wide version-%d manifest + apparatus"
                     % args.version], host_repo, write=True)
            v.commits.append((host_repo, "manifest"))
    print("  manifest -> %s (committed) and %s (canonical)"
          % (committed_manifest, canonical_manifest))

    # -- 4. tags: every module + every submodule -------------------------------
    print("\n-- version tags --")
    for m in all_modules:
        v.tag_module(m["path"], force=args.force)

    # -- 5. refresh canonical manifest with resolved tag commits ---------------
    if not args.dry_run:
        for entry in manifest["modules"]:
            entry["tag_commit"] = git.query(["rev-list", "-n", "1", manifest["tag"]],
                                            entry["path"])
        with open(canonical_manifest, "w") as fh:
            json.dump(manifest, fh, indent=2)

    print("\n== done: %d commit(s), %d tag(s) as %s =="
          % (len(v.commits), len(v.tags), v.tag))
    print("   re-run verify_git_state.py to confirm the project is still valid.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
