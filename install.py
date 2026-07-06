#!/usr/bin/env python3
"""install.py — the ratchet installer (lives on `main`, the forward-only ratchet).

`main` carries no source tree — only this installer. Running it:
  1. checks out the `source` version tag `version-<X>` (detached — materializes source@X),
  2. provisions the php runtime,
  3. installs the matching STATE from the `state` branch (state-<X> tag, else newest <=X, else HEAD),
  4. stands the CMS up and verifies,
recording telemetry per step and catching any bug thrown (Variant-A bug-report + jazz ticket).

Self-contained (stdlib only): on `main` there is no registry or tooling until the source version
is checked out, so this script embeds its own bug-report + best-effort jazz telemetry and drives
the source's registry-gated tools by subprocess.

    python3 install.py --version X [--no-verify] [--return-to-main]
"""
import argparse
import json
import os
import subprocess
import sys
import tarfile
import tempfile
import time
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))          # repo root (source-free on main)


def sh(args, **kw):
    return subprocess.run(args, cwd=HERE, capture_output=True, text=True, **kw)


def git(*args):
    return sh(["git", *args])


def run(args, what):
    r = sh(args)
    if r.returncode != 0:
        raise RuntimeError("%s failed (exit %s): %s" % (what, r.returncode, (r.stderr or r.stdout).strip()[-600:]))
    return r.stdout


# --------------------------------------------------------------------------- #
# self-contained telemetry + bug-report (pre-checkout: no registry yet)       #
# --------------------------------------------------------------------------- #
def telemetry_handles():
    try:
        cand = os.path.join(os.path.dirname(HERE), "jazz_telemetry")   # sibling package
        if cand not in sys.path:
            sys.path.insert(0, cand)
        from jazz_telemetry import telemetry, open_ticket
        return telemetry("ratchet"), open_ticket
    except Exception:  # noqa: BLE001
        return None, None


def bug_sink():
    rel = "logs/bug_reports.jsonl"
    reg = os.path.join(HERE, "registry.json")
    if os.path.isfile(reg):
        try:
            rel = json.load(open(reg)).get("bug_reports", rel)
        except Exception:  # noqa: BLE001
            pass
    return os.path.join(HERE, rel)


def bug_report(exc, tb, step):
    path = bug_sink()
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {
        "filename": os.path.basename(__file__),
        "function": last.name if last else "?",
        "time-of-occurance": datetime.now().isoformat(),
        "methods-to-reproduce": "python3 install.py %s" % " ".join(sys.argv[1:]),
        "possible-cause": "%s: %s" % (type(exc).__name__, exc),
        "traceback": tb.strip().splitlines()[-6:],
        "note": "ratchet install step: %s" % step,
    }
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass
    return path


T, OPEN_TICKET = telemetry_handles()


def step(name, fn):
    """Run one install step with telemetry + bug-catching; fail-fast (re-raises)."""
    print("\n== %s ==" % name)
    t0 = time.time()
    try:
        out = fn()
        ms = (time.time() - t0) * 1000.0
        if T:
            T.emit("crank", status="ok", ms=ms, step=name)
        print("   ok (%.0f ms)" % ms)
        return out
    except Exception as exc:  # noqa: BLE001
        tb = traceback.format_exc()
        p = bug_report(exc, tb, name)
        if T:
            T.emit("crank", status="fail", step=name, error=str(exc)[:200])
        if OPEN_TICKET:
            try:
                OPEN_TICKET("ratchet install failed at %s" % name, component="ratchet",
                            severity="high", body=tb[-1500:])
            except Exception:  # noqa: BLE001
                pass
        print("   FAIL: %s\n   bug report -> %s" % (exc, p), file=sys.stderr)
        raise


# --------------------------------------------------------------------------- #
# crank resolution + steps                                                    #
# --------------------------------------------------------------------------- #
def _vkey(v):
    try:
        return tuple(int(x) for x in v.split("."))
    except Exception:  # noqa: BLE001
        return None


def do_checkout_version(version):
    # untracked (??) leftovers are fine; only tracked modifications block a checkout.
    dirty = [l for l in git("status", "--porcelain").stdout.splitlines() if not l.startswith("??")]
    if dirty:
        raise RuntimeError("tree has uncommitted tracked changes; refusing to checkout")
    tag = "version-%s" % version
    if git("rev-parse", "-q", "--verify", tag).returncode != 0:
        raise RuntimeError("no such source version tag: %s" % tag)
    run(["git", "checkout", tag], "git checkout %s" % tag)   # detached -> materializes source@version
    return tag


def do_provision_php():
    return run(["python3", os.path.join(HERE, "checkouts", "current", "tools", "provision_php.py")], "provision_php")


def _state_spec():
    p = os.path.join(HERE, "checkouts", "current", "state", "STATE.json")
    return json.load(open(p)) if os.path.isfile(p) else {}


def _resolve_state_ref(version):
    """State for a source version: state-<version> tag, else newest state-<Y<=version>, else the
    state-branch HEAD (state:database.tar.xz)."""
    side = _state_spec().get("side_branch", "state")
    if git("rev-parse", "-q", "--verify", "state-%s" % version).returncode == 0:
        return "state-%s:database.tar.xz" % version
    tgt = _vkey(version)
    cands = [(k, t) for t in git("tag", "-l", "state-*").stdout.split()
             for k in [_vkey(t[len("state-"):])] if k and tgt and k <= tgt]
    if cands:
        return "%s:database.tar.xz" % sorted(cands)[-1][1]
    if git("cat-file", "-e", "%s:database.tar.xz" % side).returncode == 0:
        return "%s:database.tar.xz" % side
    raise RuntimeError("no state found for version %s" % version)


def do_state(version):
    expect = set(_state_spec().get("expect_tables", []))
    ref = _resolve_state_ref(version)

    # Materialize state from the side-branch tarball into the gitignored artifacts only
    # (never touch tracked files -> the working tree stays clean).
    state_dir = os.path.join(HERE, "checkouts", "current", "state")
    with tempfile.NamedTemporaryFile(suffix=".tar.xz", delete=False) as tf:
        tmp = tf.name
    try:
        with open(tmp, "wb") as fh:
            r = subprocess.run(["git", "show", ref], cwd=HERE, stdout=fh)
        if r.returncode != 0:
            raise RuntimeError("git show %s failed" % ref)
        with tarfile.open(tmp, "r:xz") as t:
            t.extractall(state_dir, filter="data")
    finally:
        os.remove(tmp)

    import sqlite3
    sqlite = os.path.join(state_dir, "congruency.sqlite")
    con = sqlite3.connect(sqlite)
    try:
        tables = {x[0] for x in con.execute("SELECT name FROM sqlite_master WHERE type='table'")}
    finally:
        con.close()
    missing = expect - tables
    if missing:
        raise RuntimeError("installed state missing tables: %s" % sorted(missing))
    return {"source": ref, "tables": sorted(tables)}


def do_standup():
    # HTTP-200 stand-up gate that writes NO tracked files (serve.py --verify) — used for the
    # --no-verify path (boot_www would modify fixes/index.json and dirty the tree).
    return run(["python3", os.path.join(HERE, "checkouts", "current", "tools", "serve.py"), "--verify"],
               "serve --verify")


def do_verify():
    return run([os.path.join(HERE, "tooling", "congruencey-tests", "verify")], "verify")


def main():
    ap = argparse.ArgumentParser(description="ratchet installer: check out a source version + stand it up")
    ap.add_argument("--version", required=True, help="source version to install, e.g. 4.059")
    ap.add_argument("--no-verify", action="store_true", help="skip the final multi-suite verify")
    ap.add_argument("--return-to-main", action="store_true", help="git checkout main when done")
    a = ap.parse_args()

    start = git("rev-parse", "--abbrev-ref", "HEAD").stdout.strip()
    version = a.version
    tag = "version-%s" % version
    print("ratchet install: %s -> source %s" % (start, tag))

    step("checkout %s" % tag, lambda: do_checkout_version(version))
    step("provision php", do_provision_php)
    step("install state", lambda: do_state(version))
    if a.no_verify:
        step("stand up", do_standup)               # serve.py --verify: HTTP 200, no tracked writes
    else:
        step("stand up + verify", do_verify)       # tooling verify: stand_up + bug_catalog + branch_cov
    if a.return_to_main:
        step("return to main", lambda: run(["git", "checkout", "main"], "git checkout main"))

    if T:
        T.emit("install", status="ok", version=version)
    print("\n== ratchet install OK — source %s stood up ==" % tag)
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except SystemExit:
        raise
    except Exception:  # noqa: BLE001 — per-step handlers already recorded; exit non-zero
        sys.exit(1)
