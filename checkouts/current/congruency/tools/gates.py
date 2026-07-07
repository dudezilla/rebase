#!/usr/bin/env python3
"""gates.py -- run the full ratchet gate suite and report pass/fail (exit 1 if any gate fails).

One command for every gate the ratchet checks each crank:
  tagcheck (renders every tag, no fatal/warning) | crawl (broken links <= the one deliberate) |
  db_verify (self-hosting archive integrity + manifest) | tostring_check | gpl_stamp (GPL header) |
  formelement_roundtrip (form-element JSON round-trip).

The HTTP gates (tagcheck, crawl) need the dev server up (tools/serve.py, default 127.0.0.1:8899).

    python3 tools/gates.py

Registry-gated; run from anywhere in the tree.
"""
import json
import os
import re
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))


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
    p = find_registry()
    reg = json.load(open(p))
    reg["__root__"] = os.path.dirname(p)
    return reg


def run(argv, cwd, timeout=180):
    try:
        r = subprocess.run(argv, cwd=cwd, capture_output=True, text=True, timeout=timeout)
        return r.returncode, (r.stdout or "") + (r.stderr or "")
    except subprocess.TimeoutExpired:
        return 124, "timed out"
    except Exception as e:  # noqa: BLE001
        return 1, str(e)


def last_line(out):
    lines = [ln for ln in out.strip().splitlines() if ln.strip()]
    return lines[-1] if lines else ""


def by_code(code, out):
    return code == 0, last_line(out)


def crawl_judge(code, out):
    m = re.search(r"broken:\s*(\d+)", out)
    n = int(m.group(1)) if m else 99
    return n <= 1, ("broken=%d (<=1 expected)" % n)


def main():
    reg = load_registry()
    cwd = os.path.join(reg["__root__"], "checkouts", "current")   # tools run relative to the app root
    php = os.path.join(reg["__root__"], reg["paths"]["php"])
    py = sys.executable

    gates = [
        ("tagcheck",    [py, "tools/tagcheck.py"],               by_code),
        ("crawl",       [py, "tools/crawl.py"],                  crawl_judge),
        ("db_verify",   [py, "tools/db_verify.py", "--manifest"], by_code),
        ("tostring",    [py, "tools/tostring_check.py"],         by_code),
        ("gpl_stamp",   [py, "tools/gpl_stamp.py"],              by_code),
        ("formelement", [php, "tools/formelement_roundtrip.php"], by_code),
    ]

    results = []
    for name, argv, judge in gates:
        code, out = run(argv, cwd)
        ok, note = judge(code, out)
        results.append((name, ok))
        print("  %-4s  %-12s  %s" % ("PASS" if ok else "FAIL", name, note))

    npass = sum(1 for _, ok in results if ok)
    print("\ngates: %d/%d passed" % (npass, len(results)))
    return 0 if npass == len(results) else 1


if __name__ == "__main__":
    sys.exit(main())
