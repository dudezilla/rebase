#!/usr/bin/env python3
"""setup.py — the installer + lifecycle (lives on `main`, the single branch).

Each `version-*` tag is ONE self-contained tree: this installer + the CMS source + the db +
the configuration object `install.json`. The lifecycle is four verbs:

    python3 setup.py install [config.json] [--version X]   # checkout version + provision php + install db (+ verify)
    python3 setup.py up                                     # bring the CMS up  (serve on host:port)
    python3 setup.py down                                   # take the CMS down (stop the server)
    python3 setup.py uninstall                              # tear the tree back to the minted crank

`install.json` is THE configuration object — a stable, first-class artifact that stamps the
release version AND carries the lifecycle params (host/port/no_verify). It is emitted by
instrumentation at mint time (`setup.py emit-config`, and mint_crank's write_install_config) and
committed into each version tag, so the verbs are config-object-driven: an orchestrator drives the
whole lifecycle by reading/writing that one object; CLI flags are only overrides. Do NOT factor
the configuration object out.

Stdlib-only; records telemetry per step and files a Variant-A bug report on any unexpected
outcome. `install` checks out a version number (ephemeral); `uninstall` returns the tree to the
minted crank, force-recovering (and bug-reporting) if bringing it up dirtied the tree.
"""
import argparse
import hashlib
import json
import os
import signal
import subprocess
import sys
import tarfile
import time
import traceback
import urllib.request
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
CONFIG_BASENAME = "install.json"     # THE configuration object — stable, first-class, never factored out
SERVE = os.path.join("checkouts", "current", "congruency", "tools", "serve.py")
PIDFILE = os.path.join("logs", "serve.pid")
# runtime artifacts materialized during install/up (all gitignored) — purged on uninstall:
RUNTIME_PATHS = [
    "tooling/congruencey-harness/php",
    "checkouts/current/state/congruency.sqlite",
    "checkouts/current/congruency/fixes/index.json",
]


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
# self-contained telemetry + Variant-A bug report                             #
# --------------------------------------------------------------------------- #
def telemetry_handles():
    try:
        cand = os.path.join(os.path.dirname(HERE), "jazz_telemetry")
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
        "methods-to-reproduce": "python3 setup.py %s" % " ".join(sys.argv[1:]),
        "possible-cause": "%s: %s" % (type(exc).__name__, exc),
        "traceback": tb.strip().splitlines()[-6:],
        "note": "setup lifecycle step: %s" % step,
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
    """Run one lifecycle step with telemetry + bug-catching; fail-fast (re-raises)."""
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
                OPEN_TICKET("setup failed at %s" % name, component="ratchet", severity="high", body=tb[-1500:])
            except Exception:  # noqa: BLE001
                pass
        print("   FAIL: %s\n   bug report -> %s" % (exc, p), file=sys.stderr)
        raise


# --------------------------------------------------------------------------- #
# the configuration object (install.json): version stamp + lifecycle params   #
# --------------------------------------------------------------------------- #
def _vkey(v):
    try:
        return tuple(int(x) for x in v.split("."))
    except Exception:  # noqa: BLE001
        return None


def newest_version_tag():
    """The version to install, EXTRACTED from live git tags — never hand-typed."""
    versions = [(k, t[len("version-"):]) for t in git("tag", "-l", "version-*").stdout.split()
                for k in [_vkey(t[len("version-"):])] if k]
    if not versions:
        raise RuntimeError("no version-* tags found; nothing to install")
    return max(versions)[1]


def committed_config(version):
    """The configuration object COMMITTED INTO the version (git show version-<v>:install.json)."""
    r = git("show", "version-%s:%s" % (version, CONFIG_BASENAME))
    if r.returncode != 0:
        return None
    try:
        return json.loads(r.stdout)
    except Exception:  # noqa: BLE001
        return None


def canonical_config(version, no_verify=False, return_to_main=False, host="0.0.0.0", port=8899, hash_track=True):
    """THE configuration object: version stamp + lifecycle params. Kept in lockstep with
    mint_crank.write_install_config — same schema so an orchestrator drives either path."""
    return {
        "version": version,
        "no_verify": bool(no_verify),
        "return_to_main": bool(return_to_main),
        "host": host,
        "port": int(port),
        "hash_track": bool(hash_track),
        "generated_by": "setup.py emit-config",
    }


def resolve_config(a):
    """Resolve the configuration object for `install`: an explicit file, else the version's
    committed install.json, else a synthesized default. CLI flags only override (opt-in)."""
    if getattr(a, "config", None):
        with open(a.config) as fh:
            cfg = json.load(fh)
        srcname = a.config
    else:
        version = getattr(a, "version", None) or newest_version_tag()
        cfg = committed_config(version)
        srcname = ("version-%s:%s" % (version, CONFIG_BASENAME)) if cfg is not None else \
                  "synthesized (no committed %s in version-%s)" % (CONFIG_BASENAME, version)
        if cfg is None:
            cfg = canonical_config(version)
    if getattr(a, "version", None):
        cfg["version"] = a.version
    if getattr(a, "no_verify", False):
        cfg["no_verify"] = True
    if getattr(a, "return_to_main", False):
        cfg["return_to_main"] = True
    cfg.setdefault("no_verify", False)
    cfg.setdefault("return_to_main", False)
    cfg.setdefault("host", "0.0.0.0")
    cfg.setdefault("port", 8899)
    if not cfg.get("version"):
        raise RuntimeError("configuration object has no 'version' and none could be resolved")
    return cfg, srcname


def lifecycle_config(a):
    """The configuration object that drives up/down/uninstall: the installed version's root
    install.json (checked out by `install`, or written by mint). host/port overridable by flags."""
    cfg = {}
    root = os.path.join(HERE, CONFIG_BASENAME)
    if os.path.isfile(root):
        try:
            cfg = json.load(open(root))
        except Exception:  # noqa: BLE001
            cfg = {}
    cfg.setdefault("host", "0.0.0.0")
    cfg.setdefault("port", 8899)
    if getattr(a, "host", None):
        cfg["host"] = a.host
    if getattr(a, "port", None):
        cfg["port"] = a.port
    return cfg


def emit_config(a):
    """INSTRUMENTATION: write a version's configuration object (version stamp + lifecycle params)."""
    version = getattr(a, "version", None) or newest_version_tag()
    cfg = canonical_config(version, no_verify=getattr(a, "no_verify", False),
                           return_to_main=getattr(a, "return_to_main", False))
    path = getattr(a, "path", None) or os.path.join(HERE, CONFIG_BASENAME)
    with open(path, "w") as fh:
        json.dump(cfg, fh, indent=2)
        fh.write("\n")
    print("emitted configuration object for version-%s -> %s" % (version, path))
    for k, v in cfg.items():
        print("   %-14s %s" % (k, v))
    return 0


# --------------------------------------------------------------------------- #
# install steps                                                               #
# --------------------------------------------------------------------------- #
def do_checkout_version(version):
    dirty = [l for l in git("status", "--porcelain").stdout.splitlines() if not l.startswith("??")]
    if dirty:
        raise RuntimeError("tree has uncommitted tracked changes; refusing to checkout")
    tag = "version-%s" % version
    if git("rev-parse", "-q", "--verify", tag).returncode != 0:
        raise RuntimeError("no such version tag: %s" % tag)
    run(["git", "checkout", tag], "git checkout %s" % tag)   # detached -> materializes the crank
    return tag


def do_provision_php():
    return run(["python3", os.path.join(HERE, "checkouts", "current", "congruency", "tools", "provision_php.py")],
               "provision_php")


def _state_spec():
    p = os.path.join(HERE, "checkouts", "current", "state", "STATE.json")
    return json.load(open(p)) if os.path.isfile(p) else {}


def do_state(version):
    """Materialize state from the db that RIDES IN the version commit (no state branch): the
    checkout put checkouts/current/state/database.tar.xz in the tree; extract it (gitignored)."""
    expect = set(_state_spec().get("expect_tables", []))
    state_dir = os.path.join(HERE, "checkouts", "current", "state")
    tarball = os.path.join(state_dir, "database.tar.xz")
    if not os.path.isfile(tarball):
        raise RuntimeError("version %s carries no in-tree database.tar.xz (state rides in the crank)" % version)
    with tarfile.open(tarball, "r:xz") as t:
        t.extractall(state_dir, filter="data")
    import sqlite3
    con = sqlite3.connect(os.path.join(state_dir, "congruency.sqlite"))
    try:
        tables = {x[0] for x in con.execute("SELECT name FROM sqlite_master WHERE type='table'")}
    finally:
        con.close()
    missing = expect - tables
    if missing:
        raise RuntimeError("installed state missing tables: %s" % sorted(missing))
    return {"tables": sorted(tables)}


def do_verify():
    return run([os.path.join(HERE, "tooling", "congruencey-tests", "verify")], "verify")


# --------------------------------------------------------------------------- #
# server helpers (up / down)                                                  #
# --------------------------------------------------------------------------- #
def _probe_host(host):
    return "127.0.0.1" if host in ("0.0.0.0", "") else host


def _is_up(host, port):
    try:
        urllib.request.urlopen("http://%s:%s/?page=catalog" % (_probe_host(host), port), timeout=1)
        return True
    except Exception:  # noqa: BLE001 — any failure (conn refused, http error) other than 2xx
        return False


def _server_pids(port):
    r = sh(["pgrep", "-f", r"-S [0-9.]*:%d" % int(port)])
    return [int(x) for x in r.stdout.split()] if r.returncode == 0 else []


# --------------------------------------------------------------------------- #
# REST hash-tracking (the FEED half): while up, seed unique markers into the   #
# live db THROUGH rest.php and record them to a gitignored sidecar. `uninstall`#
# (next crank) greps the filesystem for those markers to catch stray checkouts #
# (note-for-claude). Markers live only in the gitignored db -> a hit anywhere  #
# else on teardown means a leaked/duplicate copy.                              #
# --------------------------------------------------------------------------- #
HASH_SIDECAR = os.path.join("logs", "hashes.json")     # gitignored (logs/)
HASH_TABLE = "annotations"                             # id, tag, target, note, ts, meta
HASH_TAG = "setup:hashmark"


def _state_sqlite():
    return os.path.join(HERE, "checkouts", "current", "state", "congruency.sqlite")


def _bootstrap_api_key(sqlite):
    """api_keys ships empty; rest.php gates writes on a valid key. Insert a write key into the
    (gitignored) live db so the feed can POST. Never touches tracked files."""
    import sqlite3
    key = hashlib.sha256(os.urandom(32)).hexdigest()
    con = sqlite3.connect(sqlite)
    try:
        con.execute("INSERT INTO api_keys (key, label, scope, ts) VALUES (?,?,?,?)",
                    (key, "setup-hashfeed", "write", int(time.time())))
        con.commit()
    finally:
        con.close()
    return key


def _rest_post(host, port, table, key, obj):
    url = "http://%s:%s/?api=%s" % (_probe_host(host), port, table)
    req = urllib.request.Request(url, data=json.dumps(obj).encode(), method="POST",
                                 headers={"Content-Type": "application/json", "X-Api-Key": key})
    with urllib.request.urlopen(req, timeout=3) as r:
        return json.loads(r.read().decode())


def _feed_hashes(cfg, n=3):
    """Feed N unique hash markers into the live db THROUGH rest.php (POST ?api=annotations), and
    record them to the gitignored sidecar for the teardown search. Best-effort — never fails up."""
    try:
        host, port = cfg["host"], int(cfg["port"])
        sqlite = _state_sqlite()
        if not os.path.isfile(sqlite):
            return
        head = git("rev-parse", "HEAD").stdout.strip()
        key = _bootstrap_api_key(sqlite)
        markers, rowids = [], []
        for i in range(n):
            marker = hashlib.sha256(os.urandom(32)).hexdigest()
            res = _rest_post(host, port, HASH_TABLE, key,
                             {"tag": HASH_TAG, "target": head, "note": marker, "ts": int(time.time()),
                              "meta": json.dumps({"version": cfg.get("version"), "i": i})})
            markers.append(marker)
            rowids.append(res.get("rowid"))
        sidecar = os.path.join(HERE, HASH_SIDECAR)
        os.makedirs(os.path.dirname(sidecar), exist_ok=True)
        with open(sidecar, "w") as fh:
            json.dump({"head": head, "table": HASH_TABLE, "tag": HASH_TAG,
                       "markers": markers, "rowids": rowids, "ts": int(time.time())}, fh, indent=2)
        print("   hash-track: fed %d markers -> %s via rest.php (sidecar: %s)" % (len(markers), HASH_TABLE, HASH_SIDECAR))
    except Exception as exc:  # noqa: BLE001 — best-effort; the feed must never break `up`
        print("   hash-track: skipped (%s)" % exc, file=sys.stderr)


# --------------------------------------------------------------------------- #
# the four lifecycle verbs                                                     #
# --------------------------------------------------------------------------- #
def do_install(cfg, srcname):
    version = cfg["version"]
    tag = "version-%s" % version
    start = git("rev-parse", "--abbrev-ref", "HEAD").stdout.strip()
    print("setup install: %s -> %s\n   config: %s" % (start, tag, srcname))
    step("checkout %s" % tag, lambda: do_checkout_version(version))
    step("provision php", do_provision_php)
    step("install state", lambda: do_state(version))
    if not cfg.get("no_verify"):
        step("verify", do_verify)
    if cfg.get("return_to_main"):
        step("return to main", lambda: run(["git", "checkout", "main"], "git checkout main"))
    print("\n== install OK — %s (bring it up: python3 setup.py up) ==" % tag)
    return 0


def do_up(cfg):
    host, port = cfg["host"], int(cfg["port"])
    serve = os.path.join(HERE, SERVE)
    if not os.path.isfile(serve):
        raise RuntimeError("serve.py not found (%s) — run `python3 setup.py install` first" % SERVE)
    if _server_pids(port) or _is_up(host, port):
        print("already up on %s:%s" % (host, port))
        return 0
    log = os.path.join(HERE, "logs", "serve.log")
    os.makedirs(os.path.dirname(log), exist_ok=True)
    lf = open(log, "ab")
    proc = subprocess.Popen(["python3", serve, "--port", str(port)], cwd=HERE,
                            stdout=lf, stderr=lf, start_new_session=True)
    with open(os.path.join(HERE, PIDFILE), "w") as fh:   # process-group leader (start_new_session)
        fh.write(str(proc.pid))
    for _ in range(15):
        if _is_up(host, port):
            print("up on http://%s:%s (log: logs/serve.log)" % (host, port))
            if cfg.get("hash_track", True):
                _feed_hashes(cfg)                 # seed stray-detection markers via rest.php (best-effort)
            return 0
        time.sleep(1)
    raise RuntimeError("server did not come up on %s:%s (see logs/serve.log)" % (host, port))


def _kill(pid, sig):
    try:
        os.killpg(os.getpgid(pid), sig)     # whole group (serve.py + its php child)
    except (ProcessLookupError, PermissionError):
        try:
            os.kill(pid, sig)
        except ProcessLookupError:
            pass


def do_down(cfg):
    host, port = cfg["host"], int(cfg["port"])
    pids = set(_server_pids(port))
    pf = os.path.join(HERE, PIDFILE)
    if os.path.isfile(pf):
        try:
            pids.add(int(open(pf).read().strip()))
        except (ValueError, OSError):
            pass
    if not pids and not _is_up(host, port):
        print("not running on %s:%s" % (host, port))
        return 0
    for pid in pids:
        _kill(pid, signal.SIGTERM)
    for _ in range(10):
        if not _is_up(host, port) and not _server_pids(port):
            break
        time.sleep(0.5)
    for pid in _server_pids(port):          # last resort
        _kill(pid, signal.SIGKILL)
    try:
        os.remove(pf)
    except OSError:
        pass
    print("down (%s:%s stopped)" % (host, port))
    return 0


def _purge_runtime():
    import shutil
    for rel in RUNTIME_PATHS:
        p = os.path.join(HERE, rel)
        if os.path.isdir(p):
            shutil.rmtree(p, ignore_errors=True)
        elif os.path.isfile(p):
            try:
                os.remove(p)
            except OSError:
                pass


def _tree_dirty():
    return [l for l in git("status", "--porcelain").stdout.splitlines() if not l.startswith("??")]


def do_uninstall(cfg):
    """Return the tree to the minted crank + purge runtime. Uninstall failure is a tolerated
    anomaly: file a Variant-A bug report, then force-recover (reset --hard + purge)."""
    try:
        do_down(cfg)                        # stop the server first (best-effort)
    except Exception:  # noqa: BLE001
        pass
    crank = git("rev-parse", "HEAD").stdout.strip()
    try:
        run(["git", "checkout", "-f", "HEAD"], "git checkout -f (return to crank)")
        _purge_runtime()
        if _tree_dirty():
            raise RuntimeError("tree still dirty after uninstall: %s" % _tree_dirty()[:5])
        print("uninstalled — tree at minted crank %s, runtime purged" % crank[:10])
        return 0
    except Exception as exc:  # noqa: BLE001 — tolerated anomaly: bug-report then force-recover
        tb = traceback.format_exc()
        p = bug_report(exc, tb, "uninstall")
        print("uninstall anomaly: %s\n   bug report -> %s\n   force-recovering..." % (exc, p), file=sys.stderr)
        git("reset", "--hard", "HEAD")
        _purge_runtime()
        if _tree_dirty():
            raise RuntimeError("force-recover failed; tree still dirty: %s" % _tree_dirty()[:5])
        print("recovered — tree at minted crank %s" % crank[:10])
        return 0


def main():
    ap = argparse.ArgumentParser(
        description="setup.py — installer + lifecycle (install/up/down/uninstall), config-object-driven")
    sub = ap.add_subparsers(dest="cmd")

    pi = sub.add_parser("install", help="checkout a version + provision php + install db (+ verify)")
    pi.add_argument("config", nargs="?", default=None,
                    help="path to a configuration object (default: newest version's committed install.json)")
    pi.add_argument("--version", default=None, help="override the version, e.g. 4.081")
    pi.add_argument("--no-verify", action="store_true", help="skip the verify suite")
    pi.add_argument("--return-to-main", action="store_true", help="git checkout main when done")

    for name, helptext in (("up", "bring the CMS up (serve on host:port)"),
                           ("down", "take the CMS down (stop the server)")):
        p = sub.add_parser(name, help=helptext)
        p.add_argument("--host", default=None)
        p.add_argument("--port", type=int, default=None)

    sub.add_parser("uninstall", help="return the tree to the minted crank + purge runtime")

    pe = sub.add_parser("emit-config", help="INSTRUMENTATION: write a version's configuration object")
    pe.add_argument("path", nargs="?", default=None, help="output path (default: ./install.json)")
    pe.add_argument("--version", default=None)
    pe.add_argument("--no-verify", action="store_true")
    pe.add_argument("--return-to-main", action="store_true")

    a = ap.parse_args()
    if a.cmd is None:
        ap.print_help()
        return 2
    if a.cmd == "install":
        cfg, srcname = resolve_config(a)
        return do_install(cfg, srcname)
    if a.cmd == "up":
        return do_up(lifecycle_config(a))
    if a.cmd == "down":
        return do_down(lifecycle_config(a))
    if a.cmd == "uninstall":
        return do_uninstall(lifecycle_config(a))
    if a.cmd == "emit-config":
        return emit_config(a)
    ap.print_help()
    return 2


if __name__ == "__main__":
    try:
        sys.exit(main())
    except SystemExit:
        raise
    except Exception:  # noqa: BLE001 — per-step handlers already recorded; exit non-zero
        sys.exit(1)
