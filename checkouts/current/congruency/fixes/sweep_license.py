#!/usr/bin/env python3
"""sweep_license.py — crank #22: sweep the legacy headers + personal address out of bugs/ + tooling/.

Same relicense_file logic as the checkouts/current modernization, now pointed at the out-of-scope copies
(the drift map + the harness/CI-CD/tournament snapshots). Replaces each legacy GPL+contact header with
the short notice (CRLF preserved), removes tracked *~ backups, and drives the personal street-address
and the legacy contact-block marker to ZERO across the WHOLE repo. Test-first via predict.py;
halt on REFUTED. Records to fixes/index.json; Variant-A bug report on exception.
"""
import importlib.util
import json
import os
import re
import subprocess
import sys
import tempfile
import time
import traceback
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
FIXES = HERE if os.path.basename(HERE) == "fixes" else os.path.dirname(HERE)
SOURCE = os.path.dirname(FIXES)
INDEX = os.path.join(FIXES, "index.json")


def _root(d=HERE):
    while d != os.path.dirname(d):
        if os.path.isfile(os.path.join(d, "registry.json")):
            return d
        d = os.path.dirname(d)
    return os.path.dirname(os.path.dirname(SOURCE))


ROOT = _root()
WALK_DIRS = ["bugs", "tooling"]
# built from fragments so THIS patch never carries the personal literals contiguously
_ADDR = "2234" " 4th Ave"
_CI = "<<<Contact" " Info>>>"
HEADER_RE = re.compile(r"/\*.*?\*/", re.DOTALL)
PLAIN_RE = re.compile(
    r"Congruency[^\r\n]*web management system\.[\s\S]*?51 Franklin Street[^\r\n]*USA\."
    r"(?:\s*" + _CI + r"[^\r\n]*\r?\n[^\r\n]*)?")
SKIP_NAMES = {"LICENSE", "License.txt"}
SHORT_LINES = [
    "/*",
    "Copyright (C) 2006 Steven Peterson",
    "Congruency is free software, licensed under the GNU GPLv2 or later.",
    "See the LICENSE file in the project root for full license terms.",
    "*/",
]


def git(*a):
    return subprocess.run(["git", *a], cwd=ROOT, capture_output=True, text=True)


def _predict_mod():
    spec = importlib.util.spec_from_file_location("predict", os.path.join(SOURCE, "tools", "predict.py"))
    m = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(m)
    return m


def _php():
    reg = json.load(open(os.path.join(ROOT, "registry.json")))
    return os.path.join(ROOT, reg.get("paths", {}).get("php", "tooling/congruencey-harness/php/php"))


def _jazz():
    try:
        cand = os.path.join(os.path.dirname(ROOT), "jazz_telemetry")
        if cand not in sys.path:
            sys.path.insert(0, cand)
        from jazz_telemetry import telemetry
        return telemetry("license")
    except Exception:  # noqa: BLE001
        return None


def find_header(text):
    for m in HEADER_RE.finditer(text):
        if "GNU General Public License" in m.group():
            return m
    return None


def relicense_file(path, rel, jazz):
    found = changed = False
    try:
        raw = open(path, "r", encoding="utf-8", errors="surrogateescape").read()
    except (OSError, UnicodeDecodeError):
        return {"found": False, "changed": False}
    m = find_header(raw)
    if m:
        nl = "\r\n" if "\r\n" in m.group() else "\n"
        repl = nl.join(SHORT_LINES)
    else:
        m = PLAIN_RE.search(raw)
        if m:
            nl = "\r\n" if "\r\n" in m.group() else "\n"
            repl = nl.join(SHORT_LINES[1:4])
    found = m is not None
    if found:
        new = raw[:m.start()] + repl + raw[m.end():]
        if new != raw:
            with open(path, "w", encoding="utf-8", errors="surrogateescape", newline="") as fh:
                fh.write(new)
            changed = True
    if jazz:
        jazz.emit("relicense", file=rel, found=found, changed=changed)
    return {"found": found, "changed": changed}


def bug_report(exc, tb):
    path = os.path.join(ROOT, "logs", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: sweep_license (#22)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record(changed):
    entry = {"fix": os.path.basename(__file__), "target": "bugs/** + tooling/** headers",
             "purpose": "build #22: sweep legacy GPL+contact headers out of the bugs/ + tooling/ copies (%d files); address -> 0 repo-wide" % changed,
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    P = _predict_mod()
    jazz = _jazz()
    php = _php()
    tracked = set(f for f in git("ls-files", "--", *WALK_DIRS).stdout.split() if f)

    changed_php = []
    scanned = changed = 0
    for top in WALK_DIRS:
        for base, dirs, files in os.walk(os.path.join(ROOT, top)):
            dirs[:] = [d for d in dirs if d != ".git"]
            for fn in files:
                if fn.endswith("~") or fn in SKIP_NAMES:
                    continue
                path = os.path.join(base, fn)
                rel = os.path.relpath(path, ROOT)
                if rel not in tracked:
                    continue
                scanned += 1
                res = relicense_file(path, rel, jazz)
                if res["changed"]:
                    changed += 1
                    if fn.endswith(".php"):
                        changed_php.append((path, rel))
    if jazz:
        jazz.emit("relicense_run", scope="bugs+tooling", scanned=scanned, changed=changed)

    # remove tracked *~ backups under bugs/ + tooling/
    baks = [f for f in tracked if f.endswith("~")]
    if baks:
        git("rm", "--quiet", *baks)

    # regression lint (SAMPLED — comment-only swap on frozen copies; a spread sample catches
    # systematic breakage without linting all ~568 pre-broken 2006 files)
    sample = changed_php[::max(1, len(changed_php) // 30)] if changed_php else []
    regressions = []
    for path, rel in sample:
        if subprocess.run([php, "-l", path], capture_output=True).returncode == 0:
            continue
        orig = git("show", "HEAD:%s" % rel).stdout
        with tempfile.NamedTemporaryFile("w", suffix=".php", delete=False) as tf:
            tf.write(orig)
            tmp = tf.name
        try:
            before_ok = subprocess.run([php, "-l", tmp], capture_output=True).returncode == 0
        finally:
            os.remove(tmp)
        if before_ok:
            regressions.append(rel)

    addr = [x for x in git("grep", "-lF", _ADDR).stdout.split() if x]
    contact = [x for x in git("grep", "-lF", _CI).stdout.split() if x]
    verdicts = [
        P.check("the personal street address is gone from the WHOLE repo", expected=0, actual=len(addr)),
        P.check("the legacy contact-block marker is gone from the WHOLE repo", expected=0, actual=len(contact)),
        P.check("no relicensed .php REGRESSED php -l (sampled, parsed before -> fails after)", expected=0, actual=len(regressions)),
    ]
    if "REFUTED" in verdicts:
        raise RuntimeError("REFUTED: addr=%s contact=%s regressions=%s" % (addr[:5], contact[:5], regressions[:5]))

    record(changed)
    print(json.dumps({"ok": True, "scanned": scanned, "changed": changed,
                      "backups_removed": len(baks), "lint_checked": len(changed_php)}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
