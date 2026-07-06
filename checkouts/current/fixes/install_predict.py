#!/usr/bin/env python3
"""install_predict.py — crank 1 [build #11]: install + self-test the predictions harness.

The working tree already has tools/predict.py (written directly). This patch git-ignores the ledger
and SELF-TESTS predict.py: a matching prediction -> CONFIRMED, a mismatched -> REFUTED (open_bug=False,
no spurious ticket), both landing in logs/predictions.jsonl. Fails (bug event) if it misbehaves.
Records to fixes/index.json; Variant-A bug report on exception.
"""
import importlib.util
import json
import os
import sys
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


def bug_report(exc, tb):
    path = os.path.join(ROOT, "logs", "bug_reports.jsonl")
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "crank: install_predict (#11)"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def ensure_gitignore():
    gi = os.path.join(ROOT, ".gitignore")
    line = "/logs/predictions.jsonl"
    lines = open(gi).read().splitlines() if os.path.isfile(gi) else []
    if line not in lines:
        with open(gi, "a") as fh:
            fh.write(("" if not lines or lines[-1] == "" else "\n") + line + "\n")


def selftest():
    p = os.path.join(SOURCE, "tools", "predict.py")
    if not os.path.isfile(p):
        raise RuntimeError("tools/predict.py not present in the working tree")
    spec = importlib.util.spec_from_file_location("predict_selftest", p)
    m = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(m)

    pid = m.predict("selftest: expected 4", expected=4)
    v_confirm = m.resolve(pid, 4, open_bug=False)
    if v_confirm != "CONFIRMED":
        raise RuntimeError("matching prediction should be CONFIRMED, got %r" % v_confirm)
    v_refute = m.check("selftest: intentionally wrong", expected=1, actual=2, open_bug=False)
    if v_refute != "REFUTED":
        raise RuntimeError("mismatched prediction should be REFUTED, got %r" % v_refute)

    sink = os.path.join(ROOT, "logs", "predictions.jsonl")
    rows = [json.loads(l) for l in open(sink)] if os.path.isfile(sink) else []
    verdicts = {r.get("verdict") for r in rows if r.get("kind") == "resolve"}
    if not {"CONFIRMED", "REFUTED"} <= verdicts:
        raise RuntimeError("ledger missing verdicts, got %s" % verdicts)
    return {"confirmed": v_confirm, "refuted": v_refute, "ledger_rows": len(rows)}


def record():
    entry = {"fix": os.path.basename(__file__), "target": "checkouts/current/tools/predict.py",
             "purpose": "build #11: predictions harness (test-first prediction-vs-actual ledger) + self-test",
             "recorded": time.strftime("%Y-%m-%dT%H:%M:%S")}
    idx = json.load(open(INDEX)) if os.path.isfile(INDEX) else []
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)


def main():
    ensure_gitignore()
    res = selftest()
    record()
    print(json.dumps({"ok": True, **res}))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001
        bug_report(exc, traceback.format_exc())
        print("EXCEPTION — bug report filed", file=sys.stderr)
        raise
