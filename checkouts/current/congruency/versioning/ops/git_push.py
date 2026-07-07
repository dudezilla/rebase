#!/usr/bin/env python3
"""git_push.py — push this repository to its GitHub origin, from OUTSIDE the container.

Run this on a machine that has network access and an ssh key authorised for
git@github.com. It is self-contained (stdlib only) so it can be copied anywhere.

Why it exists: GitHub is strict about submodules — it REJECTS a superproject push whose
gitlink points to a commit the remote does not yet have. So this script (a) pushes every
submodule first, (b) then the branches, (c) then the tags, and retries transient failures
(expect several attempts). Every git call goes through run(): it uses subprocess, streams
stdout AND stderr to the screen and to a log file, and is wrapped in try/except so any
exception is recorded rather than silently swallowed.

Usage:
    python3 git_push.py                 # push configured branches + tags to origin
    python3 git_push.py --branch main   # override branch(es), repeatable
    python3 git_push.py --dry-run       # print what it would do, push nothing
"""
import argparse
import datetime
import os
import subprocess
import sys
import time

# ---------------------------------------------------------------- configuration --
REPO = os.getcwd()                                   # set at runtime to the git toplevel
REMOTE = "origin"
DEFAULT_BRANCHES = ["from-checkout-3", "order-logging"]
MAX_ATTEMPTS = 12
RETRY_DELAY_S = 5
LOG_PATH = None                                      # set at runtime

EXCEPTIONS = []                                      # (context, error) recorded here


# --------------------------------------------------------------------- logging --
def log(msg):
    """Print to screen AND append to the console log file."""
    line = "%s  %s" % (datetime.datetime.now().isoformat(timespec="seconds"), msg)
    print(line, flush=True)
    if LOG_PATH:
        try:
            with open(LOG_PATH, "a") as fh:
                fh.write(line + "\n")
        except OSError as exc:                       # never let logging crash the push
            print("(!) could not write log: %s" % exc, flush=True)


# ---------------------------------------------- subprocess-using functions -----
def run(cmd, cwd=None, dry_run=False):
    """Run a command via subprocess; stream stdout+stderr to screen+log; try/except-guarded.
    Returns (returncode, stdout, stderr). Records any exception in EXCEPTIONS."""
    cwd = cwd or REPO
    log("$ %s   (cwd=%s)" % (" ".join(cmd), cwd))
    if dry_run:
        log("  (dry-run: not executed)")
        return 0, "", ""
    try:
        proc = subprocess.run(cmd, cwd=cwd, capture_output=True, text=True)
    except Exception as exc:                         # FileNotFoundError, OSError, etc.
        log("  !! EXCEPTION: %r" % exc)
        EXCEPTIONS.append((" ".join(cmd), repr(exc)))
        return 1, "", str(exc)
    for ln in (proc.stdout or "").splitlines():
        log("  out| %s" % ln)
    for ln in (proc.stderr or "").splitlines():
        log("  err| %s" % ln)
    log("  -> rc=%d" % proc.returncode)
    return proc.returncode, proc.stdout, proc.stderr


def resolve_toplevel():
    """Set REPO to the git worktree toplevel of this script's location."""
    global REPO
    here = os.path.dirname(os.path.abspath(__file__))
    try:
        proc = subprocess.run(["git", "-C", here, "rev-parse", "--show-toplevel"],
                              capture_output=True, text=True)
        if proc.returncode == 0 and proc.stdout.strip():
            REPO = proc.stdout.strip()
    except Exception as exc:
        EXCEPTIONS.append(("resolve_toplevel", repr(exc)))
    return REPO


def show_remote(dry_run=False):
    rc, out, _ = run(["git", "remote", "-v"], dry_run=dry_run)
    return rc == 0


def list_submodules(dry_run=False):
    rc, out, _ = run(["git", "submodule", "status"], dry_run=dry_run)
    subs = []
    for line in (out or "").splitlines():
        parts = line.strip().lstrip("+-U ").split()
        if len(parts) >= 2:
            subs.append(parts[1])
    return subs


def retry(fn, what, attempts=MAX_ATTEMPTS, delay=RETRY_DELAY_S):
    """Run fn() up to `attempts` times until it returns True; record failures."""
    for i in range(1, attempts + 1):
        log("== attempt %d/%d: %s ==" % (i, attempts, what))
        try:
            if fn():
                log("== SUCCESS: %s (attempt %d) ==" % (what, i))
                return True
            log("   attempt %d for '%s' did not succeed" % (i, what))
        except Exception as exc:
            log("   !! attempt %d raised: %r" % (i, exc))
            EXCEPTIONS.append((what, repr(exc)))
        if i < attempts:
            time.sleep(delay)
    log("== GAVE UP: %s after %d attempts ==" % (what, attempts))
    return False


def push_submodules_first(dry_run=False):
    subs = list_submodules(dry_run=dry_run)
    if not subs:
        log("no submodules in this repo — nothing to push first")
        return True
    ok = True
    for sp in subs:
        sub_cwd = os.path.join(REPO, sp)
        ok = retry(lambda sp=sp, c=sub_cwd: run(["git", "push", REMOTE, "HEAD"],
                                                cwd=c, dry_run=dry_run)[0] == 0,
                   "push submodule %s" % sp) and ok
    return ok


def push_branches(branches, dry_run=False):
    ok = True
    for b in branches:
        ok = retry(lambda b=b: run(["git", "push", REMOTE, "%s:%s" % (b, b)],
                                   dry_run=dry_run)[0] == 0,
                   "push branch %s" % b) and ok
    return ok


def push_tags(dry_run=False):
    return retry(lambda: run(["git", "push", REMOTE, "--tags"], dry_run=dry_run)[0] == 0,
                 "push tags")


# ------------------------------------------------------------------------ main --
def main(argv=None):
    global LOG_PATH
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--branch", action="append", dest="branches",
                    help="branch to push (repeatable; default: %s)" % DEFAULT_BRANCHES)
    ap.add_argument("--remote", default=REMOTE)
    ap.add_argument("--no-tags", action="store_true")
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--log", default=None)
    a = ap.parse_args(argv)

    resolve_toplevel()
    LOG_PATH = a.log or os.path.join(REPO, "git_push.log")
    branches = a.branches or DEFAULT_BRANCHES

    log("### git_push.py — repo=%s remote=%s branches=%s dry_run=%s ###"
        % (REPO, a.remote, branches, a.dry_run))
    show_remote(dry_run=a.dry_run)

    results = {}
    for name, fn in [
        ("submodules-first", lambda: push_submodules_first(dry_run=a.dry_run)),
        ("branches", lambda: push_branches(branches, dry_run=a.dry_run)),
        ("tags", (lambda: True) if a.no_tags else (lambda: push_tags(dry_run=a.dry_run))),
    ]:
        try:
            results[name] = fn()
        except Exception as exc:                     # top-level guard per step
            log("!! step '%s' raised: %r" % (name, exc))
            EXCEPTIONS.append((name, repr(exc)))
            results[name] = False

    log("### SUMMARY ###")
    for name, ok in results.items():
        log("  %-16s %s" % (name, "OK" if ok else "FAILED"))
    if EXCEPTIONS:
        log("### recorded exceptions (%d) ###" % len(EXCEPTIONS))
        for ctx, err in EXCEPTIONS:
            log("  - %s :: %s" % (ctx, err))
    ok_all = all(results.values()) and not EXCEPTIONS
    log("### DONE — %s ###" % ("ALL PUSHED" if ok_all else "INCOMPLETE (see log + exceptions)"))
    return 0 if ok_all else 1


if __name__ == "__main__":
    sys.exit(main())
