#!/usr/bin/env python3
"""modernize_license.py — crank [build #18]: slim the license header project-wide.

Walks the source tree (checkouts/current — the "current folder"; tooling/harness/ci-cd are NOT touched,
not released yet). Per file, relicense_file() REPORTS whether the legacy GPL header was found, and if so
replaces that whole /* ... */ comment with a short 4-line notice, preserving CRLF/LF. Telemetry is
recorded per file + a run summary (jazz component 'license'). Writes checkouts/current/LICENSE (notice +
full GPLv2 reused from doc/License.txt), removes the tracked *~ backups, and gitignores *~.
Test-first via predict.py (halt on REFUTED). Records to fixes/index.json; Variant-A bug report on exc.
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
SOURCE = os.path.dirname(FIXES)                       # checkouts/current  (the source tree / "current folder")
INDEX = os.path.join(FIXES, "index.json")


def _root(d=HERE):
    while d != os.path.dirname(d):
        if os.path.isfile(os.path.join(d, "registry.json")):
            return d
        d = os.path.dirname(d)
    return os.path.dirname(os.path.dirname(SOURCE))


ROOT = _root()
HEADER_RE = re.compile(r"/\*.*?\*/", re.DOTALL)
# plain-text fallback (e.g. POM_README.TXT): the Congruency notice not wrapped in a /* */ comment.
PLAIN_RE = re.compile(
    r"Congruency[^\r\n]*web management system\.[\s\S]*?51 Franklin Street[^\r\n]*USA\."
    r"(?:\s*<<<Contact" r" Info>>>[^\r\n]*\r?\n[^\r\n]*)?")
SKIP_NAMES = {"LICENSE", "License.txt"}
SHORT_LINES = [
    "/*",
    "Copyright (C) 2006 Steven Peterson",
    "Congruency is free software, licensed under the GNU GPLv2 or later.",
    "See the LICENSE file in the project root for full license terms.",
    "*/",
]
NOTICE = """Congruency: The web management system.
Copyright (C) 2006 Steven Peterson

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

======================================================================
Full license text (GNU General Public License, Version 2):
======================================================================

"""


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
    """Return the match for the /* ... */ comment that carries the GPL notice, else None."""
    for m in HEADER_RE.finditer(text):
        if "GNU General Public License" in m.group():
            return m
    return None


def relicense_file(path, rel, jazz):
    """Per-file: report whether the legacy header was FOUND, and replace it if so. Emits telemetry."""
    found = changed = False
    try:
        raw = open(path, "r", encoding="utf-8", errors="surrogateescape").read()
    except (OSError, UnicodeDecodeError):
        if jazz:
            jazz.emit("relicense", file=rel, found=False, changed=False, note="unreadable")
        return {"file": rel, "found": False, "changed": False}
    m = find_header(raw)
    if m:                                              # header inside a /* ... */ comment
        nl = "\r\n" if "\r\n" in m.group() else "\n"
        replacement = nl.join(SHORT_LINES)
    else:                                              # plain-text notice (e.g. a README)
        m = PLAIN_RE.search(raw)
        if m:
            nl = "\r\n" if "\r\n" in m.group() else "\n"
            replacement = nl.join(SHORT_LINES[1:4])    # the 3 body lines, no /* */ markers
    found = m is not None
    if found:
        new = raw[:m.start()] + replacement + raw[m.end():]
        if new != raw:
            with open(path, "w", encoding="utf-8", errors="surrogateescape", newline="") as fh:
                fh.write(new)
            changed = True
    if jazz:
        jazz.emit("relicense", file=rel, found=found, changed=changed)
    return {"file": rel, "found": found, "changed": changed}


def write_license():
    src = os.path.join(SOURCE, "doc", "License.txt")
    full = open(src, "r", encoding="utf-8", errors="surrogateescape").read().replace("\r\n", "\n")
    with open(os.path.join(SOURCE, "LICENSE"), "w", encoding="utf-8", newline="\n") as fh:
        fh.write(NOTICE + full)


def remove_backups():
    bak = [f for f in git("ls-files", "--", "checkouts/current").stdout.split() if f.endswith("~")]
    if bak:
        git("rm", "--quiet", *bak)
    gi = os.path.join(ROOT, ".gitignore")
    lines = open(gi).read().splitlines() if os.path.isfile(gi) else []
    if "*~" not in lines:
        with open(gi, "a") as fh:
            fh.write(("" if not lines or lines[-1] == "" else "\n") + "*~\n")
    return bak


def bug_report(exc, tb):
    path = os.path.join(ROOT, "logs", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: modernize_license (#18)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def record(changed):
    entry = {"fix": os.path.basename(__file__), "target": "checkouts/current/**/*.php headers + LICENSE",
             "purpose": "build #18: replace %d legacy GPL+contact headers with a short notice; add root LICENSE; scrub personal contact" % changed,
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def _grep_count(needle):
    r = git("grep", "-l", "-F", needle, "--", "checkouts/current")
    return [x for x in r.stdout.split() if x]


def main():
    P = _predict_mod()
    jazz = _jazz()
    tracked = set(f for f in git("ls-files", "--", "checkouts/current").stdout.split() if f)

    # 1. walk the source tree (checkouts/current); relicense each tracked, non-backup file
    scanned = found = changed = 0
    changed_php = []
    for base, dirs, files in os.walk(SOURCE):
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
            found += res["found"]
            if res["changed"]:
                changed += 1
                if fn.endswith(".php"):
                    changed_php.append(path)
    if jazz:
        jazz.emit("relicense_run", scanned=scanned, found=found, changed=changed)

    # 2. root LICENSE (notice + full GPLv2)
    write_license()
    # 3. remove tracked *~ backups + gitignore
    baks = remove_backups()

    # 4. test-first predictions (halt on REFUTED)
    addr = _grep_count("2234" " 4th Ave")
    contact = _grep_count("<<<Contact" " Info>>>")
    franklin = set(_grep_count("51 Franklin Street"))
    allowed = {"checkouts/current/LICENSE", "checkouts/current/doc/License.txt"}
    php = _php()
    regressions = []            # files that PARSED before the swap but fail after (a real break)
    for p in changed_php:
        rel = os.path.relpath(p, ROOT)
        if subprocess.run([php, "-l", p], capture_output=True).returncode == 0:
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
            regressions.append(rel)   # broke a file that was previously valid

    verdicts = [
        P.check("0 tracked files under checkouts/current retain the legacy street-address line",
                expected=0, actual=len(addr)),
        P.check("0 tracked files retain the legacy contact-block marker", expected=0, actual=len(contact)),
        P.check("'51 Franklin Street' survives only in LICENSE + doc/License.txt",
                expected=True, actual=(franklin <= allowed)),
        P.check("no relicensed .php REGRESSED php -l (parsed before, fails after)", expected=0, actual=len(regressions)),
        P.check("root LICENSE exists at the source-tree root", expected=True,
                actual=os.path.isfile(os.path.join(SOURCE, "LICENSE"))),
    ]
    if "REFUTED" in verdicts:
        raise RuntimeError("REFUTED: addr=%s contact=%s franklin=%s regressions=%s" % (addr, contact, franklin - allowed, regressions))

    record(changed)
    print(json.dumps({"ok": True, "scanned": scanned, "found": found, "changed": changed,
                      "backups_removed": baks, "lint_checked": len(changed_php)}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
