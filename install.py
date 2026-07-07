#!/usr/bin/env python3
"""install.py — the ratchet installer (lives on `main`, the forward-only ratchet).

`main` carries no source tree — only this installer. Running it:
  1. checks out the `source` version tag `version-<X>` (detached — materializes source@X),
  2. provisions the php runtime,
  3. installs the STATE that rides IN the version commit (checkouts/current/state/database.tar.xz),
  4. stands the CMS up and verifies,
recording telemetry per step and catching any bug thrown (Variant-A bug-report + jazz ticket).

Self-contained (stdlib only): on `main` there is no registry or tooling until the source version
is checked out, so this script embeds its own bug-report + best-effort jazz telemetry and drives
the source's registry-gated tools by subprocess.

The version + arguments are NOT hand-typed: they are carried by a per-version install.json
(emitted by `--emit-config` instrumentation at mint time, committed into the version tag).
Anyone runs it with no arguments; the version and flags are read, not invented:

    python3 install.py                       # auto-resolve newest version + its committed install.json
    python3 install.py path/to/install.json  # or drive from an explicit config file
    python3 install.py --emit-config         # instrumentation: write ./install.json for the newest version
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


# --------------------------------------------------------------------------- #
# config: the version + arguments EXTRACTED to JSON (no hand-typed CLI args)   #
#                                                                             #
# `install.py --version X` only worked because a human hand-typed X and knew  #
# the flags — reproducible on one machine only. The version + arguments are   #
# instead carried by a per-version install.json (emitted by instrumentation   #
# at mint time, committed into the version tag). Anyone runs `python3         #
# install.py` with no arguments; the version and flags are read, not typed.   #
# --------------------------------------------------------------------------- #
CONFIG_BASENAME = "install.json"


def newest_version_tag():
    """The version to install, EXTRACTED from live git tags — never hand-typed."""
    versions = [(k, t[len("version-"):]) for t in git("tag", "-l", "version-*").stdout.split()
                for k in [_vkey(t[len("version-"):])] if k]
    if not versions:
        raise RuntimeError("no version-* tags found; nothing to install")
    return max(versions)[1]


def committed_config(version):
    """The install.json COMMITTED INTO the version (git show version-<v>:install.json).

    This is the per-version config generated by instrumentation at mint time; read
    straight from the tag blob, no checkout required. None if the version predates
    the machinery (graceful fallback to a synthesized default)."""
    r = git("show", "version-%s:%s" % (version, CONFIG_BASENAME))
    if r.returncode != 0:
        return None
    try:
        return json.loads(r.stdout)
    except Exception:  # noqa: BLE001
        return None


def canonical_config(version, no_verify=False, return_to_main=False):
    """The extracted-arguments record for a version: version + flags, nothing machine-specific."""
    return {
        "version": version,
        "no_verify": bool(no_verify),
        "return_to_main": bool(return_to_main),
        "generated_by": "install.py --emit-config",
    }


def resolve_config(a):
    """Resolve the effective config: an explicit file, else the version's committed
    install.json, else a synthesized default. CLI flags only override (opt-in)."""
    if a.config:
        with open(a.config) as fh:
            cfg = json.load(fh)
        src = a.config
    else:
        version = a.version or newest_version_tag()
        cfg = committed_config(version)
        if cfg is not None:
            src = "version-%s:%s" % (version, CONFIG_BASENAME)
        else:
            cfg = canonical_config(version)
            src = "synthesized (no committed %s in version-%s)" % (CONFIG_BASENAME, version)
    if a.version:
        cfg["version"] = a.version
    if a.no_verify:
        cfg["no_verify"] = True
    if a.return_to_main:
        cfg["return_to_main"] = True
    cfg.setdefault("no_verify", False)
    cfg.setdefault("return_to_main", False)
    if not cfg.get("version"):
        raise RuntimeError("config has no 'version' and none could be resolved")
    return cfg, src


def emit_config(a):
    """INSTRUMENTATION: write a version's install.json (its extracted version + args) so it
    can be committed into the version. This is what mint_crank calls prior to pushing."""
    version = a.version or newest_version_tag()
    cfg = canonical_config(version, no_verify=a.no_verify, return_to_main=a.return_to_main)
    path = a.emit_config or os.path.join(HERE, CONFIG_BASENAME)
    with open(path, "w") as fh:
        json.dump(cfg, fh, indent=2)
        fh.write("\n")
    print("emitted install config for version-%s -> %s" % (version, path))
    for k, v in cfg.items():
        print("   %-14s %s" % (k, v))
    return 0


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
    return run(["python3", os.path.join(HERE, "checkouts", "current", "congruency", "tools", "provision_php.py")], "provision_php")


def _state_spec():
    p = os.path.join(HERE, "checkouts", "current", "state", "STATE.json")
    return json.load(open(p)) if os.path.isfile(p) else {}


def do_state(version):
    """Materialize state from the db that RIDES IN the version commit (state merged into the
    crank — no state branch): the checkout put checkouts/current/state/database.tar.xz in the
    tree; extract it into the state dir (gitignored artifacts only, tree stays clean)."""
    expect = set(_state_spec().get("expect_tables", []))
    state_dir = os.path.join(HERE, "checkouts", "current", "state")
    tarball = os.path.join(state_dir, "database.tar.xz")
    if not os.path.isfile(tarball):
        raise RuntimeError("version %s carries no in-tree database.tar.xz "
                           "(state now rides in the version commit — re-mint this version)" % version)
    with tarfile.open(tarball, "r:xz") as t:
        t.extractall(state_dir, filter="data")

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
    return {"source": "in-tree database.tar.xz", "tables": sorted(tables)}


def do_standup():
    # HTTP-200 stand-up gate that writes NO tracked files (serve.py --verify) — used for the
    # --no-verify path (boot_www would modify fixes/index.json and dirty the tree).
    return run(["python3", os.path.join(HERE, "checkouts", "current", "congruency", "tools", "serve.py"), "--verify"],
               "serve --verify")


def do_verify():
    return run([os.path.join(HERE, "tooling", "congruencey-tests", "verify")], "verify")


def main():
    ap = argparse.ArgumentParser(description="ratchet installer: stand up a source version from its JSON config")
    ap.add_argument("config", nargs="?", default=None,
                    help="path to an install config JSON (default: auto-resolve the newest version + "
                         "its committed install.json — no arguments needed)")
    ap.add_argument("--version", default=None, help="override the version to install, e.g. 4.078")
    ap.add_argument("--no-verify", action="store_true", help="skip the final multi-suite verify")
    ap.add_argument("--return-to-main", action="store_true", help="git checkout main when done")
    ap.add_argument("--emit-config", nargs="?", const="", default=None, metavar="PATH",
                    help="INSTRUMENTATION: write the version's install.json (version + args) and exit; "
                         "PATH defaults to ./install.json")
    a = ap.parse_args()

    if a.emit_config is not None:
        return emit_config(a)

    cfg, src = resolve_config(a)
    version = cfg["version"]
    tag = "version-%s" % version
    start = git("rev-parse", "--abbrev-ref", "HEAD").stdout.strip()
    print("ratchet install: %s -> source %s" % (start, tag))
    print("   config: %s" % src)

    step("checkout %s" % tag, lambda: do_checkout_version(version))
    step("provision php", do_provision_php)
    step("install state", lambda: do_state(version))
    if cfg["no_verify"]:
        step("stand up", do_standup)               # serve.py --verify: HTTP 200, no tracked writes
    else:
        step("stand up + verify", do_verify)       # tooling verify: stand_up + bug_catalog + branch_cov
    if cfg["return_to_main"]:
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
