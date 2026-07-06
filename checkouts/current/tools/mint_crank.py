#!/usr/bin/env python3
"""mint_crank.py — mint a crank: inject one python patch, capture it as the next version.

A crank stroke = INJECT one author-supplied Python patch on the single `source` branch, RUN it
(its one change), and CAPTURE it (commit + version tag) — the captured patch IS the diff that
defines the next version. No branch-per-crank: a crank is a commit + tag IN PLACE. This formalises
the fixes/*.py "ratchet link" discipline into one operation.

    # precondition: on the `source` branch, tracked-clean, php provisioned
    python3 checkouts/current/tools/mint_crank.py --patch /path/to/turn_xyz.py [--name xyz] [-m MSG]

Steps (each records a BUG EVENT on ANY unexpected outcome — exception OR expected!=actual —
then fail-fast):
    precondition  on `source`, tracked-clean (no fork)
    inject        copy the python patch -> checkouts/current/fixes/<name>.py  (must compile)
    run           python3 <injected>                                          (exit 0)
    capture       add -A + commit + version-4.05x tag on `source` (reuses compute_next_version)
    state         make_state.py --version <ver>   (state:database.tar.xz + state-<ver> tag)
    verify        tooling/congruencey-tests/verify                            (0 failed)

Push is a SEPARATE release step (ticket #6) — the mint stays local. python only, registry-gated.
"""
import argparse
import os
import re
import shutil
import subprocess
import sys
import time
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))          # checkouts/current/tools
SOURCE = os.path.dirname(HERE)                             # checkouts/current
FIXES = os.path.join(SOURCE, "fixes")
VERSIONING = os.path.join(SOURCE, "versioning")


# --------------------------------------------------------------------------- #
# registry + Variant-A bug report + telemetry (reused pattern)                #
# --------------------------------------------------------------------------- #
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
    import json
    path = find_registry()
    reg = json.load(open(path))
    reg["__root__"] = os.path.dirname(path)
    return reg


def bug_report(reg, exc, tb, step):
    import json
    root = (reg or {}).get("__root__") or os.path.dirname(os.path.dirname(SOURCE))
    path = os.path.join(root, (reg or {}).get("bug_reports", "logs/bug_reports.jsonl"))
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {
        "filename": os.path.basename(__file__),
        "function": last.name if last else "?",
        "time-of-occurance": datetime.now().isoformat(),
        "methods-to-reproduce": "python3 %s %s" % (os.path.relpath(__file__, root), " ".join(sys.argv[1:])),
        "possible-cause": "%s: %s" % (type(exc).__name__, exc),
        "traceback": tb.strip().splitlines()[-6:],
        "note": "ratchet mint step: %s" % step,
    }
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass
    return path


def telemetry_handles(reg):
    try:
        pkgs = os.path.dirname(os.path.dirname(reg["__root__"]))   # .../packages
        cand = os.path.join(pkgs, "jazz_telemetry")
        if cand not in sys.path:
            sys.path.insert(0, cand)
        from jazz_telemetry import telemetry, open_ticket
        return telemetry("ratchet"), open_ticket
    except Exception:  # noqa: BLE001
        return None, None


class Unexpected(RuntimeError):
    """Raised when a step's actual outcome != expected (bug event, even without an exception)."""


# --------------------------------------------------------------------------- #
# git + subprocess helpers                                                    #
# --------------------------------------------------------------------------- #
def _root():
    return load_registry()["__root__"]


ROOT = _root()


def git(*args):
    return subprocess.run(["git", *args], cwd=ROOT, capture_output=True, text=True)


def run_py(path, *args, timeout=300):
    return subprocess.run([sys.executable, path, *args], cwd=ROOT, capture_output=True, text=True, timeout=timeout)


# --------------------------------------------------------------------------- #
# steps                                                                       #
# --------------------------------------------------------------------------- #
def step_precondition(current):
    # A crank is now a commit + tag IN PLACE on the single `source` branch (no fork).
    if current != "source":
        raise Unexpected("mint must run on the `source` branch, not %r" % current)
    if [l for l in git("status", "--porcelain").stdout.splitlines() if not l.startswith("??")]:
        raise Unexpected("tree has uncommitted tracked changes; refusing to mint")
    return current


def step_inject(patch, name):
    if not os.path.isfile(patch):
        raise Unexpected("patch not found: %s" % patch)
    base = name if name.endswith(".py") else name + ".py"
    dest = os.path.join(FIXES, base)
    if os.path.exists(dest):
        raise Unexpected("patch destination already exists: %s" % dest)
    shutil.copy2(patch, dest)
    os.chmod(dest, os.stat(dest).st_mode | 0o111)
    c = subprocess.run([sys.executable, "-m", "py_compile", dest], capture_output=True, text=True)
    if c.returncode != 0:
        raise Unexpected("injected patch does not compile: %s" % c.stderr.strip())
    return dest


def step_run(dest):
    r = run_py(dest)
    if r.returncode != 0:
        raise Unexpected("patch exited %s: %s" % (r.returncode, (r.stderr or r.stdout).strip()[-400:]))
    return {"stdout_tail": (r.stdout or "").strip().splitlines()[-3:]}


def step_capture(message):
    """Capture the patch as the next version — LEAN single-repo path.

    commit_release's collect_modules() scans the parent for child repos (root=repo -> "no
    modules"; root=packages -> stray manifest + a landmine if a sibling ever gets git-init'd),
    so we stage/commit/tag THIS repo directly and reuse version_source.compute_next_version()
    for the python-computed version (never inferred). Single folded repo, no sibling reach."""
    if VERSIONING not in sys.path:
        sys.path.insert(0, VERSIONING)
    from gitutil import Git
    from version_source import compute_next_version

    g = Git(identity={"name": "ratchet", "email": "ratchet@congruency.local"}, echo=False)
    g.run(["add", "-A"], ROOT, write=True)
    if not (g.query(["diff", "--cached", "--name-only"], ROOT) or "").strip():
        raise Unexpected("nothing staged — the patch produced no change to capture")

    tags_before = set(git("tag", "-l", "version-*").stdout.split())
    version = compute_next_version(g, ROOT)                     # python-computed from live tags
    tag = "version-%s" % version
    before = g.query(["rev-parse", "HEAD"], ROOT)
    g.run(["commit", "-m", message], ROOT, write=True)
    after = g.query(["rev-parse", "HEAD"], ROOT)
    if after == before:
        raise Unexpected("commit did not advance HEAD")
    g.run(["tag", "-a", tag, "-m", "Release %s" % version], ROOT, write=True)
    if tag not in (set(git("tag", "-l", "version-*").stdout.split()) - tags_before):
        raise Unexpected("expected new tag %s to be created" % tag)
    return {"version": version, "tag": tag, "committed_in": ["congruencey"], "commit": after[:10]}


def step_state(version):
    r = run_py(os.path.join(HERE, "make_state.py"), "--version", version)
    if r.returncode != 0:
        raise Unexpected("make_state failed: %s" % (r.stderr or r.stdout).strip()[-400:])
    if git("cat-file", "-e", "state:database.tar.xz").returncode != 0:
        raise Unexpected("state:database.tar.xz not committed")
    return {"state_ref": "state:database.tar.xz"}


def step_verify():
    r = subprocess.run([os.path.join(ROOT, "tooling", "congruencey-tests", "verify")],
                       cwd=ROOT, capture_output=True, text=True, timeout=300)
    m = re.search(r"(\d+)\s+suites passed,\s+(\d+)\s+failed", r.stdout)
    if not m or int(m.group(2)) != 0:
        raise Unexpected("verify not clean: %s" % (r.stdout or r.stderr).strip()[-400:])
    return {"passed": int(m.group(1)), "failed": int(m.group(2))}


# --------------------------------------------------------------------------- #
# driver                                                                      #
# --------------------------------------------------------------------------- #
def main():
    ap = argparse.ArgumentParser(description="mint a crank from one python patch")
    ap.add_argument("--patch", required=True, help="path to the python patch script (the turn)")
    ap.add_argument("--name", default=None, help="injected filename under fixes/ (default: patch basename)")
    ap.add_argument("-m", "--message", default=None, help="commit/release message")
    a = ap.parse_args()

    reg = load_registry()
    T, open_ticket = telemetry_handles(reg)
    name = a.name or os.path.splitext(os.path.basename(a.patch))[0]
    message = a.message or ("crank: %s" % name)
    current = git("rev-parse", "--abbrev-ref", "HEAD").stdout.strip()

    def do(step_name, fn):
        print("\n== %s ==" % step_name)
        t0 = time.time()
        try:
            out = fn()
            ms = (time.time() - t0) * 1000.0
            if T:
                T.emit("mint", status="ok", ms=ms, step=step_name)
            print("   ok (%.0f ms) %s" % (ms, out if out else ""))
            return out
        except Exception as exc:  # noqa: BLE001 — Unexpected or any error is a bug event
            tb = traceback.format_exc()
            p = bug_report(reg, exc, tb, step_name)
            if T:
                T.emit("mint", status="fail", step=step_name, error=str(exc)[:200])
            if open_ticket:
                try:
                    open_ticket("ratchet mint failed at %s" % step_name, component="ratchet",
                                severity="high", body=tb[-1500:], step=step_name)
                except Exception:  # noqa: BLE001
                    pass
            print("   FAIL: %s\n   bug report -> %s" % (exc, p), file=sys.stderr)
            raise

    print("mint crank: on %s, patch=%s" % (current, a.patch))
    do("precondition", lambda: step_precondition(current))
    dest = do("inject", lambda: step_inject(a.patch, name))
    do("run patch", lambda: step_run(dest))
    cap = do("capture (commit + tag)", lambda: step_capture(message))
    do("state", lambda: step_state(cap["version"]))
    do("verify", lambda: step_verify())

    if T:
        T.emit("mint", status="ok", branch=current, version=cap.get("version"))
    print("\n== crank minted on %s: %s (commit + tag in place; state + verify green) ==" % (current, cap.get("tag")))
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except SystemExit:
        raise
    except Exception:  # noqa: BLE001 — per-step handler already recorded the bug event
        sys.exit(1)
