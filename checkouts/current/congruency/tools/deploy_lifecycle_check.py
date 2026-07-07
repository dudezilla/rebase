#!/usr/bin/env python3
"""deploy_lifecycle_check.py -- verify the production deploy lifecycle end-to-end.

Drives deploy.py (which lives on branch `main`, beside install.py) through the full
ON -> SERVE -> OFF -> REDEPLOY cycle in an ISOLATED git worktree, so the caller's working
tree and branch are never touched. Regression gate for ticket #26.

    python3 tools/deploy_lifecycle_check.py            # latest version-* tag
    DEPLOY_TEST_VERSION=4.078 python3 tools/deploy_lifecycle_check.py

Exit 0 iff every transition verifies (>=4 checks pass). Self-contained (stdlib only).
"""
import os
import re
import shutil
import signal
import socket
import subprocess
import sys
import tempfile
import time
import urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))                 # checkouts/current/tools
ROOT = os.path.abspath(os.path.join(HERE, "..", "..", ".."))     # the package/repo root
PHP = os.path.join(ROOT, "tooling", "congruencey-harness", "php", "php")


def sh(args, cwd=None):
    return subprocess.run(args, cwd=cwd or ROOT, capture_output=True, text=True)


def free_port():
    s = socket.socket(); s.bind(("127.0.0.1", 0)); p = s.getsockname()[1]; s.close(); return p


def probe(port):
    try:
        r = urllib.request.urlopen("http://127.0.0.1:%d/?page=catalog" % port, timeout=5)
        return r.status, r.read(4000).decode("utf-8", "replace")
    except Exception as e:  # noqa: BLE001
        return None, type(e).__name__


def latest_version():
    tags = sh(["git", "tag", "-l", "version-*"]).stdout.split()
    vers = [t[len("version-"):] for t in tags]

    def key(v):
        try:
            return tuple(int(x) for x in v.split("."))
        except Exception:  # noqa: BLE001
            return ()
    if not vers:
        raise SystemExit("no version-* tags found")
    return sorted(vers, key=key)[-1]


def main():
    version = os.environ.get("DEPLOY_TEST_VERSION") or latest_version()
    port = free_port()
    work = tempfile.mkdtemp(prefix="deploy_lc_")
    wt = os.path.join(work, "wt")
    target = os.path.join(work, "target")
    results = []
    served_pid = None

    def check(name, ok, detail=""):
        results.append(ok)
        print("  %s  %s%s" % ("PASS" if ok else "FAIL", name, ("  [%s]" % detail) if detail else ""))

    try:
        # isolated worktree on `main` (where deploy.py lives) -> caller's tree untouched
        r = sh(["git", "worktree", "add", wt, "main"])
        if r.returncode != 0:
            check("worktree add main", False, r.stderr.strip()[:120]); return 1
        # symlink the provisioned php so deploy's provision step skips the download
        os.makedirs(os.path.join(wt, "tooling", "congruencey-harness", "php"), exist_ok=True)
        link = os.path.join(wt, "tooling", "congruencey-harness", "php", "php")
        if not os.path.exists(link) and os.path.isfile(PHP):
            os.symlink(PHP, link)
        deploy = os.path.join(wt, "deploy.py")
        if not os.path.isfile(deploy):
            check("deploy.py present on main", False, "missing"); return 1

        # ON -- deploy a fresh production target and self-verify (stub up, dev content gone)
        r = sh(["python3", deploy, "--target", target, "--version", version, "--port", str(port)])
        last = (r.stdout.strip().splitlines() or [""])[-1]
        check("ON: deploy verifies (exit0 + 'deploy OK')",
              r.returncode == 0 and "production deploy OK" in r.stdout,
              last[:80] if r.returncode else "")

        # SERVE -- detached server on the pinned config port
        r = sh(["python3", deploy, "--serve", "--target", target])
        m = re.search(r"pid (\d+)", r.stdout)
        served_pid = int(m.group(1)) if m else None
        time.sleep(2)
        s, b = probe(port)
        check("SERVE: up (200 + stub 'Welcome')", s == 200 and "Welcome" in b, "status=%s" % s)

        # OFF -- stop the server; the port must go dead
        if served_pid:
            try:
                os.kill(served_pid, signal.SIGTERM)
            except Exception:  # noqa: BLE001
                pass
            time.sleep(1.5)
        s, _ = probe(port)
        check("OFF: server down after stop", s is None, "resp=%s" % s)
        if s is None:
            served_pid = None

        # REDEPLOY -- same target: idempotent reuse (no rebuild) + re-verify
        r = sh(["python3", deploy, "--target", target, "--version", version, "--port", str(port)])
        check("REDEPLOY: idempotent (reused + exit0)",
              r.returncode == 0 and "target reused" in r.stdout, "")
    finally:
        if served_pid:
            try:
                os.kill(served_pid, signal.SIGKILL)
            except Exception:  # noqa: BLE001
                pass
        sh(["git", "worktree", "remove", "--force", wt])
        shutil.rmtree(work, ignore_errors=True)

    npass = sum(1 for ok in results if ok)
    n = len(results)
    print("deploy lifecycle: %d/%d transitions verified (version-%s)" % (npass, n, version))
    return 0 if (npass == n and n >= 4) else 1


if __name__ == "__main__":
    sys.exit(main())
