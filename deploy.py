#!/usr/bin/env python3
"""deploy.py — the production release deployer (lives on `main`, beside install.py).

install.py stands up a source version for DEV/TEST against the demo DB (the ratchet). deploy.py
DEPLOYS a source version to a target folder for PRODUCTION: it exports the app, writes a JSON
install config, seeds a FRESH production STUB database, and boots config-driven — recording each
verification as a PREDICTION vs ACTUAL (a REFUTED prediction is a bug + halt).

    python3 deploy.py --target /srv/site --version 4.070 [--port N] [--keep-serving]

Flow (per the plan):
  1. checkout `version-X` (detached) to materialize the source.
  2. if <target>/install.json is ABSENT: create the target, export the app (minus the ratchet
     apparatus + dev DB), write install.json {db: <target>/state/congruency.sqlite}, and run
     prod_seed.php to build a fresh stub DB there.
  3. boot <target>/boot/router.php (config_loader reads <target>/install.json -> the prod DB) and
     probe: home is the stub intro; dev pages (?page=bugs) are gone (404 stub).
Self-contained (stdlib only): main carries no source until the checkout. Best-effort jazz telemetry.
"""
import argparse
import json
import os
import shutil
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))          # repo root (main)
APP_SRC = os.path.join("checkouts", "current")             # the app within the source tree
# ratchet/dev bits NOT shipped to a production target:
EXCLUDE_DIRS = {"fixes", "versioning", "tools", "tests", "__pycache__"}
EXCLUDE_STATE = {"congruency.sqlite", "database.tar.xz", "seed.php", "STATE.json"}
PAGES = None  # set at runtime


def sh(args, **kw):
    return subprocess.run(args, cwd=HERE, capture_output=True, text=True, **kw)


def git(*a):
    return sh(["git", *a])


def run(args, what):
    r = sh(args)
    if r.returncode != 0:
        raise RuntimeError("%s failed (exit %s): %s" % (what, r.returncode, (r.stderr or r.stdout).strip()[-500:]))
    return r.stdout


# --------------------------------------------------------------------------- #
# telemetry + prediction ledger (self-contained; component=deploy)            #
# --------------------------------------------------------------------------- #
def _jazz():
    try:
        cand = os.path.join(os.path.dirname(HERE), "jazz_telemetry")
        if cand not in sys.path:
            sys.path.insert(0, cand)
        from jazz_telemetry import telemetry, open_ticket
        return telemetry("deploy"), open_ticket
    except Exception:  # noqa: BLE001
        return None, None


T, OPEN_TICKET = _jazz()


def predict(statement, expected, actual):
    """Record prediction vs actual -> verdict CONFIRMED/REFUTED (logs/predictions.jsonl + jazz)."""
    sink = os.path.join(HERE, "logs", "predictions.jsonl")
    verdict = "CONFIRMED" if actual == expected else "REFUTED"
    try:
        os.makedirs(os.path.dirname(sink), exist_ok=True)
        with open(sink, "a") as fh:
            fh.write(json.dumps({"kind": "resolve", "component": "deploy",
                                 "ts": time.strftime("%Y-%m-%dT%H:%M:%S"), "statement": statement,
                                 "expected": expected, "actual": actual, "verdict": verdict}) + "\n")
    except OSError:
        pass
    if T:
        T.emit("predict", status=verdict.lower(), statement=statement[:120])
    if verdict == "REFUTED" and OPEN_TICKET:
        try:
            OPEN_TICKET("deploy prediction REFUTED: %s" % statement[:120], component="deploy",
                        severity="high", kind="bug", expected=repr(expected)[:200], actual=repr(actual)[:200])
        except Exception:  # noqa: BLE001
            pass
    print("   %s  %s" % (verdict, statement))
    return verdict


# --------------------------------------------------------------------------- #
# steps                                                                       #
# --------------------------------------------------------------------------- #
def resolve_version(explicit):
    if explicit:
        return explicit
    tags = [t[len("version-"):] for t in git("tag", "-l", "version-*").stdout.split()]
    def key(v):
        try:
            return tuple(int(x) for x in v.split("."))
        except Exception:  # noqa: BLE001
            return ()
    if not tags:
        raise RuntimeError("no version-* tags found")
    return sorted(tags, key=key)[-1]


def checkout_version(version):
    dirty = [l for l in git("status", "--porcelain").stdout.splitlines() if not l.startswith("??")]
    if dirty:
        raise RuntimeError("tree has uncommitted tracked changes; refusing to checkout")
    tag = "version-%s" % version
    if git("rev-parse", "-q", "--verify", tag).returncode != 0:
        raise RuntimeError("no such source version tag: %s" % tag)
    run(["git", "checkout", tag], "git checkout %s" % tag)
    return tag


def provision_php():
    run(["python3", os.path.join(HERE, "checkouts", "current", "tools", "provision_php.py")], "provision_php")
    return os.path.join(HERE, "tooling", "congruencey-harness", "php", "php")


def _ignore(dirpath, names):
    drop = set(n for n in names if n in EXCLUDE_DIRS)
    if os.path.basename(dirpath) == "state":
        drop |= {n for n in names if n in EXCLUDE_STATE}
    return drop


def build_target(target, php):
    """Create the target + export app + install.json + fresh stub DB (only if not already configured)."""
    cfg = os.path.join(target, "install.json")
    if os.path.isfile(cfg):
        return {"created": False, "config": cfg, "note": "target already configured"}
    os.makedirs(target, exist_ok=True)
    # export the app (checkouts/current/* -> target/*), minus the ratchet apparatus + dev DB
    src = os.path.join(HERE, APP_SRC)
    for name in os.listdir(src):
        if name in EXCLUDE_DIRS:
            continue
        s, d = os.path.join(src, name), os.path.join(target, name)
        if os.path.isdir(s):
            shutil.copytree(s, d, ignore=_ignore, dirs_exist_ok=True)
        else:
            shutil.copy2(s, d)
    # config -> the prod DB path (the one that matters under the sqlite shim)
    db = os.path.join(os.path.abspath(target), "state", "congruency.sqlite")
    os.makedirs(os.path.dirname(db), exist_ok=True)
    with open(cfg, "w") as fh:
        json.dump({"db": db, "site": {}}, fh, indent=2)
    # seed the FRESH production stub DB
    seed = os.path.join(target, "state", "prod_seed.php")
    if not os.path.isfile(seed):
        raise RuntimeError("prod_seed.php missing in exported app (%s)" % seed)
    r = subprocess.run([php, seed], cwd=os.path.join(target, "state"), capture_output=True, text=True,
                       timeout=60, env=dict(os.environ, CONGRUENCY_SQLITE=db))
    if r.returncode != 0 or not os.path.isfile(db):
        raise RuntimeError("prod_seed failed (exit %s): %s" % (r.returncode, (r.stderr or r.stdout)[:300]))
    return {"created": True, "config": cfg, "db": db}


def _free_port():
    s = socket.socket(); s.bind(("127.0.0.1", 0)); p = s.getsockname()[1]; s.close(); return p


def _probe(url):
    try:
        with urllib.request.urlopen(url, timeout=8) as r:
            return r.status, r.read(8000).decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read(8000).decode("utf-8", "replace")
    except Exception as e:  # noqa: BLE001
        return None, "%r" % e


def boot_and_verify(target, php, port, keep):
    proc = subprocess.Popen([php, "-S", "127.0.0.1:%d" % port, "boot/router.php"], cwd=target,
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    time.sleep(1.5)
    try:
        s_home, b_home = _probe("http://127.0.0.1:%d/?page=catalog" % port)
        s_bug, b_bug = _probe("http://127.0.0.1:%d/?page=bugs" % port)
    finally:
        if not keep:
            proc.terminate()
            try:
                proc.communicate(timeout=5)
            except Exception:  # noqa: BLE001
                proc.kill()
    v1 = predict("prod home (?page=catalog) is 200 with the stub intro", True,
                 (s_home == 200 and "Welcome" in b_home and "resurrection" not in b_home))
    v2 = predict("dev pages are gone (?page=bugs -> 404 stub, no BugReport)", True,
                 ("Page not found" in b_bug and "BugReport" not in b_bug and "SQL injection" not in b_bug))
    ok = (v1 == "CONFIRMED" and v2 == "CONFIRMED")
    return ok, {"home": s_home, "bugs_len": len(b_bug), "port": port, "serving": keep and proc.pid or None}


def main():
    ap = argparse.ArgumentParser(description="deploy a source version to a production target folder")
    ap.add_argument("--target", required=True, help="target deploy folder (created if absent)")
    ap.add_argument("--version", default=None, help="source version (default: latest version-* tag)")
    ap.add_argument("--port", type=int, default=None, help="serve port (default: ephemeral)")
    ap.add_argument("--keep-serving", action="store_true", help="leave the server running after verify")
    a = ap.parse_args()

    version = resolve_version(a.version)
    target = os.path.abspath(os.path.expanduser(a.target))
    print("deploy: source version-%s -> %s" % (version, target))
    try:
        tag = checkout_version(version)
        php = provision_php()
        built = build_target(target, php)
        print("   target %s (%s)" % ("built" if built.get("created") else "reused", built.get("config")))
        ok, info = boot_and_verify(target, php, a.port or _free_port(), a.keep_serving)
        if T:
            T.emit("deploy", status="ok" if ok else "fail", version=version, target=target)
        run(["git", "checkout", "main"], "return to main")
        if not ok:
            raise RuntimeError("deploy verification REFUTED (see predictions): %s" % info)
        print("\n== production deploy OK — version-%s at %s (stub site up; dev content absent) ==" % (version, target))
        return 0
    except Exception:
        try:
            git("checkout", "main")
        except Exception:  # noqa: BLE001
            pass
        raise


if __name__ == "__main__":
    try:
        sys.exit(main())
    except SystemExit:
        raise
    except Exception as exc:  # noqa: BLE001
        print("DEPLOY FAILED: %s" % exc, file=sys.stderr)
        sys.exit(1)
