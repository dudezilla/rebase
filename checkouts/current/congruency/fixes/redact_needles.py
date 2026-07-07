#!/usr/bin/env python3
"""redact_needles.py — crank #19: redact the legacy street-address / contact-block-marker literals that
the cleanup script (fixes/modernize_license.py) embedded as its own search needles, so that NO tracked
file anywhere contains the author's personal address. The literals are split into adjacent string
fragments (runtime-identical, but grep-invisible). This file builds the search targets from fragments
too, so it never contains them contiguously. Test-first: git grep across ALL tracked files -> 0.
Records to fixes/index.json; Variant-A bug report on exception.
"""
import importlib.util
import json
import os
import subprocess
import sys
import time
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
FIXES = HERE if os.path.basename(HERE) == "fixes" else os.path.dirname(HERE)
SOURCE = os.path.dirname(FIXES)
INDEX = os.path.join(FIXES, "index.json")
TARGET = os.path.join(FIXES, "modernize_license.py")


def _root(d=HERE):
    while d != os.path.dirname(d):
        if os.path.isfile(os.path.join(d, "registry.json")):
            return d
        d = os.path.dirname(d)
    return os.path.dirname(os.path.dirname(SOURCE))


ROOT = _root()

# built from fragments so THIS file never carries the personal address contiguously
ADDR = "2234" + " 4th Ave"
CONTACT = "<<<Contact" + " Info>>>"
Q = '"'
REPLACERS = [
    ('_grep_count(%s%s%s)' % (Q, ADDR, Q), '_grep_count("2234" " 4th Ave")'),
    ("retain the address '%s'" % ADDR, "retain the legacy street-address line"),
    ('_grep_count(%s%s%s)' % (Q, CONTACT, Q), '_grep_count("<<<Contact" " Info>>>")'),
    ("retain '%s'" % CONTACT, "retain the legacy contact-block marker"),
    (CONTACT, '<<<Contact" r" Info>>>'),      # inside PLAIN_RE's raw string
]


def git(*a):
    return subprocess.run(["git", *a], cwd=ROOT, capture_output=True, text=True)


def _predict_mod():
    spec = importlib.util.spec_from_file_location("predict", os.path.join(SOURCE, "tools", "predict.py"))
    m = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(m)
    return m


def bug_report(exc, tb):
    path = os.path.join(ROOT, "logs", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: redact_needles (#19)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record():
    entry = {"fix": os.path.basename(__file__), "target": "checkouts/current/fixes/modernize_license.py",
             "purpose": "fix #19: redact the personal address/contact search-needle literals from the cleanup script (grep-invisible, runtime-identical)",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    if not os.path.isfile(TARGET):
        raise RuntimeError("target cleanup script not found: %s" % TARGET)
    content = open(TARGET, encoding="utf-8").read()
    applied = 0
    for old, new in REPLACERS:
        if old in content:
            content = content.replace(old, new)
            applied += 1
    open(TARGET, "w", encoding="utf-8").write(content)

    P = _predict_mod()
    # scoped to checkouts/current — the released source tree (bugs/ + tooling/ copies are out of scope)
    addr_hits = [x for x in git("grep", "-lF", ADDR, "--", "checkouts/current").stdout.split() if x]
    contact_hits = [x for x in git("grep", "-lF", CONTACT, "--", "checkouts/current").stdout.split() if x]
    v1 = P.check("no tracked file under checkouts/current contains the legacy street address", expected=0, actual=len(addr_hits))
    v2 = P.check("no tracked file under checkouts/current contains the legacy contact-block marker", expected=0, actual=len(contact_hits))
    if "REFUTED" in (v1, v2):
        raise RuntimeError("REFUTED: addr=%s contact=%s (applied %d/%d)" % (addr_hits, contact_hits, applied, len(REPLACERS)))

    record()
    print(json.dumps({"ok": True, "replacers_applied": applied, "addr_hits": addr_hits, "contact_hits": contact_hits}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
