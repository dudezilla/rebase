#!/usr/bin/env python3
"""doc_watch.py -- file a "documentation may be stale" ticket when a commit changes code but no doc.

An outside-the-app observer of commits. For each commit where a SCRIPT (.php/.py) changed but NO doc
(.md/.txt) was touched in that same commit, it maps the changed code area -> the specific doc(s) likely
now stale (a dir->doc map) and opens/append a `documentation` ticket in the unified jazz tracker
(~/.jazz/congruency.sqlite). One OPEN ticket per doc (deduped by meta.doc); each commit is recorded once
(meta.commits), so the standalone tool and the post-commit hook converge without duplicates.

Registry-gated (walks up to registry.json), python-only, best-effort (never blocks a commit).

    python3 tools/doc_watch.py                 # watermark..HEAD  (HEAD only on first run)
    python3 tools/doc_watch.py --commit <sha>  # one commit (used by the post-commit hook)
    python3 tools/doc_watch.py --head          # HEAD only
    python3 tools/doc_watch.py --since <ref>   # ref..HEAD  (batch catch-up)
    python3 tools/doc_watch.py --dry-run       # classify + print, write nothing
    python3 tools/doc_watch.py --install-hook  # drop a post-commit hook that calls --head
"""
import argparse
import json
import os
import subprocess
import sys
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = None   # repo root (dir of registry.json); set in main()

# code areas whose change implicates a doc (longest-prefix wins per file)
DOC_MAP = [
    ("checkouts/current/boot/",         ["DEPENDENCIES.md", "DEPLOY.md", "checkouts/current/ARCHITECTURE.md"]),
    ("checkouts/current/state/",        ["DEPLOY.md", "DEPENDENCIES.md"]),
    ("checkouts/current/invocators/",   ["checkouts/current/ARCHITECTURE.md", "checkouts/current/doc/CMS_ADDITIONS.md"]),
    ("checkouts/current/lib/",          ["checkouts/current/ARCHITECTURE.md"]),
    ("checkouts/current/bin/",          ["checkouts/current/ARCHITECTURE.md"]),
    ("checkouts/current/www/",          ["checkouts/current/ARCHITECTURE.md"]),
    ("checkouts/current/tools/",        ["checkouts/current/tools/README.md"]),   # + filename special-cases
    ("checkouts/current/versioning/",   ["checkouts/current/versioning/README.md"]),
    ("checkouts/current/fixes/",        ["checkouts/current/fixes/README.md"]),
    ("checkouts/current/tests/parser/", ["checkouts/current/tests/parser/README.md"]),
    ("tracker/",                        ["tracker/SIGNALS.md"]),
    ("tooling/coverage/",               ["tooling/coverage/README.md", "tooling/README.md"]),
    ("tooling/pwdriver/",               ["tooling/pwdriver/README.md", "tooling/README.md"]),
    ("ENTRY_POINT.py",                  ["README.md"]),
    ("registry.json",                   ["README.md"]),
    ("note-for-claude",                 ["README.md"]),
]
FALLBACK_CODE = "checkouts/current/ARCHITECTURE.md"     # code under the app tree with no better hit
GENERIC = "__generic__"                                  # anything else -> a generic "review docs" ticket
IGNORE_SUBSTR = ("tooling/congruencey/", "tooling/tournament-", "/vendor/")   # frozen / vendored snapshots
SCRIPT_EXT = (".php", ".py")
DOC_EXT = (".md", ".txt")


# ---------------------------------------------------------------- registry + jazz
def find_registry(start=HERE):
    d = os.path.abspath(start)
    while True:
        cand = os.path.join(d, "registry.json")
        if os.path.isfile(cand):
            return cand
        parent = os.path.dirname(d)
        if parent == d:
            raise FileNotFoundError("registry.json not found at/above %s" % start)
        d = parent


def load_registry():
    path = find_registry()
    reg = json.load(open(path))
    reg["__root__"] = os.path.dirname(path)
    return reg


def _jazz(reg):
    """Best-effort import of the canonical ticket writer (same package predict.py/mint_crank.py use)."""
    for cand in (os.path.join(os.path.dirname(reg["__root__"]), "jazz_telemetry"),                    # .../packages/jazz_telemetry
                 os.path.join(os.path.dirname(os.path.dirname(reg["__root__"])), "jazz_telemetry")):  # predict.py's shim path
        if os.path.isdir(cand) and cand not in sys.path:
            sys.path.insert(0, cand)
    try:
        from jazz_telemetry import open_ticket, update_ticket, tickets, get_ticket
        return {"open_ticket": open_ticket, "update_ticket": update_ticket,
                "tickets": tickets, "get_ticket": get_ticket}
    except Exception as e:  # noqa: BLE001
        sys.stderr.write("[doc_watch] jazz_telemetry unavailable: %r\n" % e)
        return None


# ---------------------------------------------------------------- git helpers
def git(*args):
    return subprocess.run(["git"] + list(args), cwd=ROOT, capture_output=True, text=True).stdout.strip()


def resolve(ref):
    return git("rev-parse", ref)


def commits_in_range(start, end="HEAD"):
    out = git("rev-list", "--reverse", "%s..%s" % (start, end))
    return out.split() if out else []


def changed_files(sha):
    out = git("diff-tree", "--no-commit-id", "--name-only", "-r", sha)
    return out.splitlines() if out else []


def subject(sha):
    return git("log", "-1", "--format=%s", sha)


# ---------------------------------------------------------------- classification
def ignored(path):
    return any(s in path for s in IGNORE_SUBSTR)


def docs_for(path):
    best = None
    for prefix, docs in DOC_MAP:
        if path.startswith(prefix) and (best is None or len(prefix) > len(best[0])):
            best = (prefix, docs)
    if best is not None:
        docs = list(best[1])
        if best[0] == "checkouts/current/tools/":
            base = os.path.basename(path)
            if base in ("provision_php.py", "predict.py"):
                docs.append("DEPENDENCIES.md")
            if base in ("crawl.py", "tagcheck.py"):
                docs.append("checkouts/current/doc/CMS_ADDITIONS.md")
        return docs
    if path.startswith("checkouts/current/") and path.endswith(SCRIPT_EXT):
        return [FALLBACK_CODE]
    return [GENERIC]


# ---------------------------------------------------------------- dedup state
def load_open_docs(jazz):
    """map implicated-doc -> {id, meta, body} for OPEN documentation tickets already filed by doc_watch."""
    out = {}
    if not jazz:
        return out
    for t in jazz["tickets"]("OPEN"):
        if t.get("component") != "documentation":
            continue
        full = jazz["get_ticket"](t["id"])
        if not full:
            continue
        meta = full.get("meta")
        if isinstance(meta, dict) and meta.get("doc"):
            out[meta["doc"]] = {"id": full["id"], "meta": meta, "body": full.get("body") or ""}
    return out


def title_for(doc):
    return "Docs may be stale: %s" % ("review documentation" if doc == GENERIC else doc)


def process_commit(sha, jazz, open_docs, write):
    files = [f for f in changed_files(sha) if not ignored(f)]
    scripts = [f for f in files if f.endswith(SCRIPT_EXT)]
    docs_touched = [f for f in files if f.endswith(DOC_EXT)]
    if not scripts or docs_touched:
        return []   # trigger only on "code changed, docs didn't"
    implicated = {}
    for s in scripts:
        for d in docs_for(s):
            implicated.setdefault(d, set()).add(s)
    subj = subject(sha)
    actions = []
    for doc in sorted(implicated):
        scr = sorted(implicated[doc])
        line = "%s %s -- scripts: %s" % (sha[:9], subj, ", ".join(scr))
        if doc in open_docs:
            t = open_docs[doc]
            commits = list(t["meta"].get("commits", []))
            if sha in commits:
                actions.append(("skip", doc, t["id"]))
                continue
            commits.append(sha)
            body = (t["body"] + "\n" + line).strip()
            if write:
                jazz["update_ticket"](t["id"], body=body, doc=doc, commits=commits, source="doc_watch")
            t["meta"]["commits"] = commits
            t["body"] = body
            actions.append(("append", doc, t["id"]))
        else:
            tid = "(dry)"
            if write:
                tid = jazz["open_ticket"](title_for(doc), component="documentation", severity="low",
                                          body=line, doc=doc, commits=[sha], source="doc_watch")
            open_docs[doc] = {"id": tid, "meta": {"doc": doc, "commits": [sha]}, "body": line}
            actions.append(("open", doc, tid))
    return actions


# ---------------------------------------------------------------- watermark + hook
def watermark_path(reg):
    return os.path.join(reg["__root__"], "logs", "doc_watch.json")


def read_watermark(reg):
    try:
        return json.load(open(watermark_path(reg))).get("last_seen")
    except Exception:  # noqa: BLE001
        return None


def write_watermark(reg, sha):
    try:
        p = watermark_path(reg)
        os.makedirs(os.path.dirname(p), exist_ok=True)
        json.dump({"last_seen": sha, "updated": datetime.now().isoformat()}, open(p, "w"), indent=2)
    except Exception:  # noqa: BLE001
        pass


HOOK = """#!/usr/bin/env python3
# doc_watch post-commit hook (installed by `doc_watch.py --install-hook`). Best-effort; never blocks.
import subprocess, sys, os
try:
    root = subprocess.run(["git", "rev-parse", "--show-toplevel"],
                          capture_output=True, text=True, timeout=10).stdout.strip()
    tool = os.path.join(root, "checkouts", "current", "tools", "doc_watch.py")
    if root and os.path.isfile(tool):
        subprocess.run([sys.executable, tool, "--head"], timeout=30)
except Exception:
    pass
"""


def install_hook(reg):
    gitdir = git("rev-parse", "--absolute-git-dir")
    hooks = os.path.join(gitdir, "hooks")
    os.makedirs(hooks, exist_ok=True)
    path = os.path.join(hooks, "post-commit")
    with open(path, "w") as fh:
        fh.write(HOOK)
    os.chmod(path, 0o755)
    print("installed post-commit hook -> %s  (runs doc_watch.py --head after each commit)" % path)
    return 0


# ---------------------------------------------------------------- main
def main():
    global ROOT
    ap = argparse.ArgumentParser(description="file a documentation-stale ticket when code changes without a doc update")
    ap.add_argument("--commit", help="inspect a single commit")
    ap.add_argument("--head", action="store_true", help="inspect HEAD only")
    ap.add_argument("--since", help="inspect <ref>..HEAD")
    ap.add_argument("--dry-run", action="store_true", help="classify + print, write nothing")
    ap.add_argument("--install-hook", action="store_true", help="install the post-commit hook and exit")
    a = ap.parse_args()

    reg = load_registry()
    ROOT = reg["__root__"]

    if a.install_hook:
        return install_hook(reg)

    if a.head or a.commit:
        commits = [resolve(a.commit or "HEAD")]
    elif a.since:
        commits = commits_in_range(a.since)
    else:
        wm = read_watermark(reg)
        commits = commits_in_range(wm) if wm else [resolve("HEAD")]

    jazz = _jazz(reg)
    write = (not a.dry_run) and (jazz is not None)
    open_docs = load_open_docs(jazz)

    actions = []
    for sha in commits:
        actions += process_commit(sha, jazz, open_docs, write)

    for kind, doc, tid in actions:
        print("  %-7s %-45s ticket %s" % (kind, doc, tid))
    tag = " [dry-run]" if a.dry_run else ("" if write else " [no jazz_telemetry — not written]")
    print("doc_watch: %d commit(s), %d action(s)%s" % (len(commits), len(actions), tag))

    # advance the watermark only for modes that logically processed up to HEAD (not a targeted --commit)
    if write and commits and a.commit is None:
        write_watermark(reg, resolve("HEAD"))
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as e:  # noqa: BLE001  -- best-effort: never crash a commit hook
        sys.stderr.write("[doc_watch] %r\n" % e)
        sys.exit(0)
