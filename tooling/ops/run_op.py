#!/usr/bin/env python3
"""run_op.py — execute an operation script; on failure, populate a bug report.

The pattern: every operation is a python script exposing `run(**kwargs)`. This runner
executes exactly one operation, and:
  * on success → prints OK and the returned result;
  * on ANY exception → catches it and appends a structured entry to bug_reports.jsonl
    (and echoes it), then exits non-zero.

This partially guards against hallucination / context drift: an operation whose
assumptions don't match live reality fails loudly and is recorded as a bug, rather than
silently diverging. Nothing is done "from memory" — the operation runs live git via
the instrumented gitutil.

Usage:
  ./run_op.py OP_SCRIPT.py [--args '{"k": "v"}'] [--bugs bug_reports.jsonl]
"""
import argparse
import importlib.util
import json
import os
import sys
import time
import traceback

OPS_DIR = os.path.dirname(os.path.abspath(__file__))


def load_operation(path):
    spec = importlib.util.spec_from_file_location("operation", path)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def record_bug(bugs_path, op_path, kwargs, exc, tb):
    entry = {
        "ts": time.strftime("%Y-%m-%dT%H:%M:%S"),
        "op": os.path.abspath(op_path),
        "kwargs": kwargs,
        "error_type": type(exc).__name__,
        "error": str(exc),
        "traceback": tb.strip().splitlines()[-6:],  # tail of the trace
    }
    with open(bugs_path, "a") as fh:
        fh.write(json.dumps(entry) + "\n")
    return entry


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("op", help="path to the operation script (exposes run(**kwargs))")
    ap.add_argument("--args", default="{}", help="JSON object of kwargs for run()")
    ap.add_argument("--bugs", default=os.path.join(OPS_DIR, "bug_reports.jsonl"))
    a = ap.parse_args(argv)

    kwargs = json.loads(a.args)
    mod = load_operation(a.op)
    if not hasattr(mod, "run"):
        print("operation %s exposes no run()" % a.op)
        return 2

    print("== run_op: %s  args=%s ==" % (os.path.basename(a.op), kwargs))
    try:
        result = mod.run(**kwargs)
        print("\nOK — operation succeeded")
        print("  result: %s" % json.dumps(result, default=str)[:800])
        return 0
    except Exception as exc:  # noqa: BLE001 — the whole point is to catch everything
        tb = traceback.format_exc()
        entry = record_bug(a.bugs, a.op, kwargs, exc, tb)
        print("\nFAILED — bug report appended to %s" % a.bugs)
        print("  %s: %s" % (entry["error_type"], entry["error"]))
        return 1


if __name__ == "__main__":
    sys.exit(main())
