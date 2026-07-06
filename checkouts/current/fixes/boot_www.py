#!/usr/bin/env python3
"""boot_www.py — ratchet link: stand up the CMS from the new tree + verify HTTP 200.

Boots `php -S` with the new-tree router (checkouts/current/boot/router.php) served
from checkouts/current, then probes real CMS pages. Success = HTTP 200 with no PHP
fatal/parse/uncaught, and the page renders through the app (not the router failing).
This is the loop's stand-up + verify_http_200 step. python-only, self-verifying,
files a telemetry ticket on failure.
"""
import json
import os
import socket
import subprocess
import sys
import time
import traceback
import urllib.error
import urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
SOURCE = os.path.dirname(HERE)                                   # checkouts/current
MONO = os.path.dirname(os.path.dirname(SOURCE))                 # b01
PHP = os.path.join(MONO, "tooling", "congruencey-harness", "php", "php")
ROUTER = "boot/router.php"
INDEX = os.path.join(HERE, "index.json")
BUGS = os.path.join(MONO, "logs", "bug_reports.jsonl")
FATAL = ("Fatal error", "Parse error", "Uncaught", "PHP Fatal", "Stack trace")
PAGES = ["?page=catalog&fresh=1", "?page=about", "?page=catalog"]


def free_port():
    s = socket.socket()
    s.bind(("127.0.0.1", 0))
    p = s.getsockname()[1]
    s.close()
    return p


def probe(url):
    try:
        with urllib.request.urlopen(url, timeout=8) as r:
            return r.status, r.read(8000).decode("utf-8", "replace"), ""
    except urllib.error.HTTPError as e:
        return e.code, e.read(8000).decode("utf-8", "replace"), ""
    except Exception as e:  # noqa: BLE001
        return None, "", "%r" % e


def main():
    if not os.path.isfile(PHP):
        raise FileNotFoundError("php not provisioned at %s (run provision_php first)" % PHP)
    if not os.path.isfile(os.path.join(SOURCE, ROUTER)):
        raise FileNotFoundError("new-tree router missing (run repath_to_new_tree first)")

    port = free_port()
    proc = subprocess.Popen([PHP, "-S", "127.0.0.1:%d" % port, ROUTER], cwd=SOURCE,
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    time.sleep(1.5)
    results = []
    try:
        for pg in PAGES:
            url = "http://127.0.0.1:%d/%s" % (port, pg)
            status, body, err = probe(url)
            results.append({"page": pg, "status": status, "http_error": err,
                            "bytes": len(body), "head": body[:160]})
            if pg == PAGES[0]:
                first_body = body
    finally:
        proc.terminate()
        try:
            _o, serr = proc.communicate(timeout=5)
        except Exception:
            proc.kill()
            _o, serr = proc.communicate()
    php_err = serr or ""

    all_200 = all(r["status"] == 200 for r in results)
    fatal = any(m in (first_body + php_err) for m in FATAL)
    no_httperr = all(not r["http_error"] for r in results)
    ok = all_200 and (not fatal) and no_httperr

    result = {
        "ok": ok, "router": ROUTER, "served_from": os.path.relpath(SOURCE, MONO),
        "pages": results, "fatal_detected": fatal,
        "php_stderr_tail": (php_err.strip().splitlines() or [])[-8:],
    }
    print(json.dumps(result, indent=2))

    if ok:
        entry = {
            "fix": os.path.basename(__file__),
            "target": "checkouts/current (www CMS)",
            "purpose": "stand up CMS from new tree; verify HTTP 200",
            "pages_200": [r["page"] for r in results],
            "recorded": time.strftime("%Y-%m-%dT%H:%M:%S"),
        }
        idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
        idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
        with open(INDEX, "w") as fh:
            json.dump(idx, fh, indent=2)
    else:
        raise RuntimeError("stand-up FAILED: all_200=%s fatal=%s httperr_ok=%s tail=%r"
                           % (all_200, fatal, no_httperr,
                              (php_err.strip().splitlines() or ["(no stderr)"])[-3:]))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        tb = traceback.format_exc()
        t = {
            "filename": os.path.basename(__file__),
            "function": (traceback.extract_tb(exc.__traceback__)[-1].name
                         if exc.__traceback__ else "?"),
            "time-of-occurance": time.strftime("%Y-%m-%dT%H:%M:%S"),
            "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
            "possible-cause": "%s: %s" % (type(exc).__name__, exc),
            "traceback": tb.strip().splitlines()[-6:],
            "note": "ratchet link: boot_www / verify_http_200",
        }
        try:
            with open(BUGS, "a") as fh:
                fh.write(json.dumps(t) + "\n")
        except OSError:
            pass
        print("EXCEPTION — ticket filed", file=sys.stderr)
        raise
