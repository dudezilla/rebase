#!/usr/bin/env python3
"""Operation: generate MANIFEST.md — a complete, unambiguous description of every
top-level file and folder in the repo (and of the tournament entry folder), so there is
no confusion about what gets pushed to git.

Lists each entry with its type, whether it is committed (and therefore pushed) or
gitignored (not pushed), and a curated description. RAISES if any committed top-level
entry has no description — the manifest is only trustworthy if it is exhaustive.
Instrumented via gitutil. Left on disk as a reusable tool.
"""
import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(HERE))          # the versioning/ dir
from gitutil import Git  # noqa: E402
from paths import entry_hash  # noqa: E402


class OperationError(Exception):
    pass


ENTRY_SUBMISSION_DESC = ("THE TOURNAMENT SUBMISSION — the commit-named entry folder. Holds "
                         "the evolved tag engine, its oracle, the zero-JS showcase, the python "
                         "versioning/ops apparatus, and a self-contained snapshot of the CMS. "
                         "See its README.md / submission_00.txt.")


ROOT_DESC = {
    ".gitignore": "Ignore rules — the generated/local files that are NOT committed or pushed (sqlite DBs, node_modules, dev caches, the pinned .oldcode/vendor).",
    "MANIFEST.md": "This file — generated inventory of every root entry and exactly what gets pushed to git.",
    "README.md": "Repository readme.",
    "ENTRANTS.txt": "Tournament entrants log — one datetime line per entry (append-only).",
    "bin": "Original congruency CMS: bootstrap/front-controller entry (Execute.php), POM init, and test harnesses.",
    "doc": "Original congruency documentation (Install / Version / Changes / License / NextVersion).",
    "etc": "Original congruency configuration (Constants.php, Privilege.php).",
    "invocators": "Original congruency tag invocators — the tag classes the parser dispatches to.",
    "lib": "Original congruency library — TagLoader/parser, DAOs, persistent-object manager, form system.",
    "www": "Original congruency web root (index.php, Constants.php).",
    "dev": "Development tooling (NOT the CMS): Playwright attack loop, the 15-bug catalogue + repros, and the old-code reference. Heavy/generated parts are gitignored.",
}

ENTRY_DESC = {
    "README.md": "Submission overview, run instructions, and the seven-goal coverage map.",
    "LINEAGE.md": "How the code lineage (original -> refactors -> submission) is assembled into one modular git repo.",
    "submission_00.txt": "Submission location relative to the main codebase + tournament protocol details.",
    "assemble_lineage.sh": "Apparatus: builds the modular lineage repo (original -> refactors -> submission), deterministic.",
    "package_tournament.sh": "Apparatus: serializes the entire tournament into one self-verifying git bundle.",
    "bundle_push.sh": "Apparatus: pushes every project repo to a local git bundle (the offline stand-in remote).",
    "serve_override.php": "auto_prepend that points the harness ABS_PATH/TAGS_DIR at this submission snapshot.",
    "showcase": "The redesigned, server-side, zero-JavaScript front-end rendered by the evolved engine (the default congruency home).",
    "tests": "The self-contained oracle and all tested data (tests/parser/: run.php + 38 datasets + the evolved engine).",
    "versioning": "The python apparatus: verify_git_state, version_source (python-computed versions), checkout, the importable `ops` package (run_op/run_plan/opsrun + commit_release/refresh_bundles/verify_bundles/propagate_version/git_commit/generate_manifest/git_push), and instrumentation logs.",
    "bin": "Snapshot of the CMS bootstrap/harness at the entry commit (so the submission evaluates in isolation).",
    "doc": "Snapshot of the CMS documentation at the entry commit.",
    "etc": "Snapshot of the CMS configuration at the entry commit.",
    "invocators": "Snapshot of the CMS tag invocators at the entry commit.",
    "lib": "Snapshot of the CMS library at the entry commit.",
    "www": "Snapshot of the CMS web root at the entry commit.",
}

KIND = {"tree": "dir", "blob": "file", "commit": "submodule"}


def _entries(git, repo, treeish):
    raw = git.query(["ls-tree", treeish], repo) or ""
    out = []
    for line in raw.splitlines():
        meta, name = line.split("\t", 1)
        _, typ, _ = meta.split()
        out.append((name, KIND.get(typ, typ)))
    return sorted(out)


def _table(entries, descs, undescribed):
    rows = ["| entry | type | pushed? | description |", "|---|---|---|---|"]
    for name, kind in entries:
        disp = name + ("/" if kind == "dir" else "")
        desc = descs.get(name, "**UNDESCRIBED**")
        if desc == "**UNDESCRIBED**":
            undescribed.append(name)
        pushed = "no (submodule ref)" if kind == "submodule" else "yes"
        rows.append("| `%s` | %s | %s | %s |" % (disp, kind, pushed, desc))
    return "\n".join(rows)


def run(repo=None, out=None, remote_url="git@github.com:dudezilla/congruencey.git", log=None):
    git = Git(logfile=log, echo=False)
    if repo is None:
        repo = git.query(["rev-parse", "--show-toplevel"], HERE)
    if not repo:
        raise OperationError("cannot resolve repo toplevel")
    out = out or os.path.join(repo, "MANIFEST.md")

    eh = entry_hash()
    branch = git.query(["rev-parse", "--abbrev-ref", "HEAD"], repo)
    head = git.query(["rev-parse", "HEAD"], repo)
    root = _entries(git, repo, "HEAD")
    entry_entries = _entries(git, repo, "HEAD:%s" % eh)
    ignore = git.query(["show", "HEAD:.gitignore"], repo) or "(none)"
    has_submods = bool(git.query(["ls-tree", "HEAD", ".gitmodules"], repo))

    root_desc = dict(ROOT_DESC)
    root_desc[eh] = ENTRY_SUBMISSION_DESC              # entry-hash described dynamically (BUG-V05)
    undescribed = []
    root_tbl = _table(root, root_desc, undescribed)
    entry_tbl = _table(entry_entries, ENTRY_DESC, [])  # entry-folder descriptions are advisory

    doc = """# MANIFEST — what is in this repository (and what gets pushed)

_Generated by `versioning/ops/generate_manifest.py`. Repo `%s` @ branch `%s` (`%s`)._

This repository (`%s`) pushes to **`%s`**. Everything listed as **pushed = yes** below is
committed and travels on `git push`. Everything under *What is NOT pushed* is gitignored.

## Root entries

%s

## Inside the submission folder `%s/`

The tournament entry. Its own top-level:

%s

## What is NOT pushed (gitignore)

These patterns are generated/local and never committed:

```
%s
```

## Submodules

%s

## Pushing to GitHub (reiterated)

`origin` = `%s`. GitHub is strict about submodules: it rejects a superproject push whose
gitlink points to a commit the remote does not yet have. Push submodules first, then the
superproject, then tags:

```sh
# from a clone on a machine with network + ssh to github.com:
git -C <repo> push origin --recurse-submodules=on-demand <branch>   # or push each submodule first
git -C <repo> push origin <branch>          # e.g. from-checkout-3  (the version-2.000x line)
git -C <repo> push origin --tags            # the version-* tags
```

Use the bundled **`versioning/ops/git_push.py`** to do this robustly (retries, streams
stdout+stderr to screen and log, records exceptions) from **outside** this container.
""" % (repo, branch, (head or "")[:12], os.path.basename(repo), remote_url,
       root_tbl, eh, entry_tbl, ignore,
       ("This repo has **no submodules of its own** (no `.gitmodules`), so its own push is not "
        "submodule-gated. The pristine original source is a submodule of a *different* repo "
        "(`congruencey-bugs/vendor/congruencey`), pinned at the 2006 `old-code` commit."
        if not has_submods else
        "This repo HAS submodules (`.gitmodules` present) — push each submodule's commit to its "
        "upstream BEFORE pushing this superproject, or GitHub will reject the push."),
       remote_url)

    if undescribed:
        raise OperationError("undescribed committed root entries (add to ROOT_DESC): %s" % undescribed)

    with open(out, "w") as fh:
        fh.write(doc)
    return {"manifest": out, "root_entries": len(root),
            "entry_entries": len(entry_entries), "has_submodules": has_submods,
            "branch": branch}


if __name__ == "__main__":
    import json
    print(json.dumps(run(), indent=2, default=str))
