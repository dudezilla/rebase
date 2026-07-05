#!/usr/bin/env python3
"""make_state.py — deterministically (re)create this crank's state and commit it to the
`state` side-branch.

Per checkouts/note-for-claude the DB ships compressed and is installed on checkout. This is
the missing PRODUCER: it runs the committed seed generator under the provisioned php to
build a fresh congruency.sqlite, tars it (with seed.php) into database.tar.xz, and commits
that single blob to the orphan `state` branch at <crank>/database.tar.xz — via git PLUMBING
only (hash-object/read-tree/write-tree/commit-tree/update-ref), so the working tree never
switches (one source tree at a time).

python only, registry-gated (throws if it can't see registry.json), auto bug-report on
exception (Variant-A), best-effort jazz telemetry.

    python3 checkouts/current/tools/make_state.py [--crank b0X] [--no-commit]
"""
import argparse
import json
import os
import shutil
import sqlite3
import subprocess
import sys
import tarfile
import tempfile
import time
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))          # checkouts/current/tools
SOURCE = os.path.dirname(HERE)                             # checkouts/current
STATE = os.path.join(SOURCE, "state")
STATE_SPEC = os.path.join(STATE, "STATE.json")


# --------------------------------------------------------------------------- #
# registry (mandated: throw if not found) + Variant-A bug report              #
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
    path = find_registry()
    reg = json.load(open(path))
    reg["__root__"] = os.path.dirname(path)
    return reg


def bug_report(reg, exc, tb, note="ratchet crank: make_state"):
    root = (reg or {}).get("__root__") or os.path.dirname(os.path.dirname(SOURCE))
    path = os.path.join(root, (reg or {}).get("bug_reports", "file-system-repair/bug_reports.jsonl"))
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {
        "filename": os.path.basename(__file__),
        "function": last.name if last else "?",
        "time-of-occurance": datetime.now().isoformat(),
        "methods-to-reproduce": "python3 %s %s" % (os.path.relpath(__file__, root), " ".join(sys.argv[1:])),
        "possible-cause": "%s: %s" % (type(exc).__name__, exc),
        "traceback": tb.strip().splitlines()[-6:],
        "note": note,
    }
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass
    return path


def telemetry_handles(reg):
    """Best-effort jazz_telemetry (component 'ratchet'); returns (emit_handle, open_ticket)
    or (None, None). Never raises."""
    try:
        pkgs = os.path.dirname(os.path.dirname(reg["__root__"]))   # .../packages
        cand = os.path.join(pkgs, "jazz_telemetry")
        if cand not in sys.path:
            sys.path.insert(0, cand)
        from jazz_telemetry import telemetry, open_ticket           # noqa: E402
        return telemetry("ratchet"), open_ticket
    except Exception:  # noqa: BLE001
        return None, None


# --------------------------------------------------------------------------- #
# state production                                                            #
# --------------------------------------------------------------------------- #
def find_php(reg):
    env = os.environ.get("CONGRUENCEY_PHP")
    if env and os.path.isfile(env):
        return env
    rel = (reg.get("paths", {}) or {}).get("php", "tooling/congruencey-harness/php/php")
    p = os.path.join(reg["__root__"], rel)
    if not os.path.isfile(p):
        raise FileNotFoundError("php not provisioned at %s (run provision_php.py first)" % p)
    return p


def build_tarball(reg, php, tmp):
    """Re-seed deterministically into `tmp` and return (tar_path, tables)."""
    spec = json.load(open(STATE_SPEC)) if os.path.isfile(STATE_SPEC) else {}
    seed_src = os.path.join(reg["__root__"], spec.get("seed", "tooling/congruencey-harness/seed.php"))
    if not os.path.isfile(seed_src):
        raise FileNotFoundError("seed generator missing: %s" % seed_src)
    seed_tmp = os.path.join(tmp, "seed.php")
    shutil.copy2(seed_src, seed_tmp)

    r = subprocess.run([php, seed_tmp], cwd=tmp, capture_output=True, text=True, timeout=60)
    sqlite_tmp = os.path.join(tmp, "congruency.sqlite")
    if r.returncode != 0 or not os.path.isfile(sqlite_tmp):
        raise RuntimeError("seed failed (exit %s): %s" % (r.returncode, (r.stderr or r.stdout).strip()[:400]))

    con = sqlite3.connect(sqlite_tmp)
    try:
        tables = [x[0] for x in con.execute(
            "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")]
    finally:
        con.close()
    missing = set(spec.get("expect_tables", [])) - set(tables)
    if missing:
        raise RuntimeError("seeded DB missing expected tables: %s" % sorted(missing))

    tar_path = os.path.join(tmp, "database.tar.xz")
    with tarfile.open(tar_path, "w:xz") as t:
        t.add(sqlite_tmp, arcname="congruency.sqlite")   # flat members: install_state_db extractall()s to state/
        t.add(seed_tmp, arcname="seed.php")
    return tar_path, tables


def commit_to_state_branch(reg, tar_path, crank, side_branch="state"):
    """Commit the tarball blob to <side_branch>:<crank>/database.tar.xz via plumbing only —
    no checkout, no worktree, so the working tree stays on the crank."""
    root = reg["__root__"]

    def git(args, env=None):
        return subprocess.run(["git"] + args, cwd=root, capture_output=True, text=True, env=env)

    blob = git(["hash-object", "-w", tar_path]).stdout.strip()
    if not blob:
        raise RuntimeError("git hash-object failed for %s" % tar_path)

    ref = "refs/heads/%s" % side_branch
    head = git(["rev-parse", "--verify", "-q", ref]).stdout.strip() or None

    tmpidx = tar_path + ".idx"
    env = dict(os.environ, GIT_INDEX_FILE=tmpidx,
               GIT_AUTHOR_NAME="ratchet", GIT_AUTHOR_EMAIL="ratchet@congruency.local",
               GIT_COMMITTER_NAME="ratchet", GIT_COMMITTER_EMAIL="ratchet@congruency.local")
    try:
        git(["read-tree", head] if head else ["read-tree", "--empty"], env=env)
        path_in = "%s/database.tar.xz" % crank
        u = git(["update-index", "--add", "--cacheinfo", "100644,%s,%s" % (blob, path_in)], env=env)
        if u.returncode != 0:
            raise RuntimeError("update-index: %s" % u.stderr.strip())
        tree = git(["write-tree"], env=env).stdout.strip()
        if not tree:
            raise RuntimeError("write-tree produced no tree")
        msg = "state(%s): re-seeded database.tar.xz" % crank
        ct = ["commit-tree", tree, "-m", msg] + (["-p", head] if head else [])
        commit = git(ct, env=env).stdout.strip()
        if not commit:
            raise RuntimeError("commit-tree failed")
        up = git(["update-ref", ref, commit])
        if up.returncode != 0:
            raise RuntimeError("update-ref: %s" % up.stderr.strip())
    finally:
        try:
            os.remove(tmpidx)
        except OSError:
            pass
    return {"branch": side_branch, "path": path_in, "blob": blob[:10],
            "commit": commit[:10], "parent": (head or "(orphan)")[:10]}


def main():
    ap = argparse.ArgumentParser(description="produce + store a crank's state on the `state` side-branch")
    ap.add_argument("--crank", default=None, help="crank name (default: current branch)")
    ap.add_argument("--no-commit", action="store_true", help="build + verify the tarball but don't commit it")
    a = ap.parse_args()

    reg = load_registry()
    php = find_php(reg)
    crank = a.crank or subprocess.run(["git", "rev-parse", "--abbrev-ref", "HEAD"],
                                      cwd=reg["__root__"], capture_output=True, text=True).stdout.strip()
    spec = json.load(open(STATE_SPEC)) if os.path.isfile(STATE_SPEC) else {}
    side = spec.get("side_branch", "state")

    T, _ = telemetry_handles(reg)
    t0 = time.time()
    with tempfile.TemporaryDirectory() as tmp:
        tar_path, tables = build_tarball(reg, php, tmp)
        result = {"crank": crank, "tables": tables, "artifact_bytes": os.path.getsize(tar_path)}
        if not a.no_commit:
            result.update(commit_to_state_branch(reg, tar_path, crank, side))
    if T:
        T.emit("make_state", status="ok", ms=(time.time() - t0) * 1000.0, crank=crank)
    print(json.dumps({"ok": True, **result}, indent=2))
    return 0


if __name__ == "__main__":
    _reg = None
    try:
        _reg = load_registry()
    except Exception:  # noqa: BLE001
        pass
    try:
        sys.exit(main())
    except SystemExit:
        raise
    except Exception as exc:  # noqa: BLE001
        tb = traceback.format_exc()
        p = bug_report(_reg, exc, tb)
        T, open_ticket = telemetry_handles(_reg) if _reg else (None, None)
        if T:
            T.emit("make_state", status="fail", note=str(exc)[:200])
        if open_ticket:
            try:
                open_ticket("ratchet make_state failed", component="ratchet", severity="high", body=tb[-1500:])
            except Exception:  # noqa: BLE001
                pass
        print("EXCEPTION — bug report -> %s" % p, file=sys.stderr)
        raise
