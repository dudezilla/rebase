"""congruency versioning ops — an importable operations library.

Each operation is a submodule exposing ``run(**kwargs)``. This package wraps them with
bug-report-on-failure so a caller can drive everything from Python:

    import ops
    ops.run_op("refresh_bundles", expect_tag="version-2.0002")
    ops.run_plan([
        {"name": "commit", "op": "commit_release", "args": {"message": "..."}},
        {"name": "bundles", "op": "refresh_bundles", "args": {"expect_tag": "version-2.0002"}},
        {"name": "verify",  "op": "verify_bundles"},
    ])

Or from a shell (single launch, still the library underneath):

    PYTHONPATH=<versioning-dir> python3 -m ops PLAN.json
    PYTHONPATH=<versioning-dir> python3 -m ops --op refresh_bundles --args '{"expect_tag": "version-2.0002"}'

On any exception the failure is appended to bug_reports.jsonl (guards against silent
context drift) and re-raised. The op submodules and the shared helpers (gitutil,
verify_git_state, version_source) live one directory up and are put on sys.path here.
"""
import importlib
import json
import os
import sys
import time
import traceback

_HERE = os.path.dirname(os.path.abspath(__file__))
_VERSIONING = os.path.dirname(_HERE)
if _VERSIONING not in sys.path:                     # make gitutil / verify_git_state / version_source importable
    sys.path.insert(0, _VERSIONING)

BUG_LOG = os.path.join(_HERE, "bug_reports.jsonl")

__all__ = ["run_op", "run_plan", "load_op", "cli", "OpError", "BUG_LOG"]


class OpError(Exception):
    pass


def _record_bug(op, kwargs, exc, tb, bugs=None):
    entry = {
        "ts": time.strftime("%Y-%m-%dT%H:%M:%S"),
        "op": op,
        "kwargs": kwargs,
        "error_type": type(exc).__name__,
        "error": str(exc),
        "traceback": tb.strip().splitlines()[-6:],
    }
    with open(bugs or BUG_LOG, "a") as fh:
        fh.write(json.dumps(entry) + "\n")
    return entry


def load_op(name):
    """Import an op submodule by name ('refresh_bundles' or 'refresh_bundles.py')."""
    base = os.path.basename(name)
    modname = base[:-3] if base.endswith(".py") else base
    return importlib.import_module("%s.%s" % (__name__, modname))


def run_op(name, bugs=None, **kwargs):
    """Run one op's run(**kwargs). On failure, record a bug report and re-raise."""
    mod = load_op(name)
    if not hasattr(mod, "run"):
        raise OpError("operation %r exposes no run()" % name)
    try:
        return mod.run(**kwargs)
    except Exception as exc:  # noqa: BLE001 — record everything, then propagate
        _record_bug(name, kwargs, exc, traceback.format_exc(), bugs)
        raise


def run_plan(steps, bugs=None):
    """Run a sequence of steps [{op, args, name}] fail-fast. Returns list of results;
    raises OpError at the first failing step (after the bug is recorded)."""
    if not isinstance(steps, list) or not steps:
        raise OpError("plan must be a non-empty list of steps")
    results = []
    for i, step in enumerate(steps, 1):
        name = step.get("name", step["op"])
        try:
            result = run_op(step["op"], bugs=bugs, **step.get("args", {}))
            results.append({"step": name, "ok": True, "result": result})
        except Exception as exc:  # noqa: BLE001
            results.append({"step": name, "ok": False, "error": str(exc)})
            raise OpError("plan aborted at step %d/%d (%s): %s"
                          % (i, len(steps), name, exc))
    return results


def cli(argv=None):
    """Shared CLI used by __main__.py and the opsrun.py executable entry point.

        <entry> PLAN.json
        <entry> --op refresh_bundles --args '{"log": "..."}'
    """
    import argparse
    ap = argparse.ArgumentParser(prog="ops")
    ap.add_argument("plan", nargs="?", help="JSON plan file (list of {op, args, name})")
    ap.add_argument("--op", help="run a single op by name")
    ap.add_argument("--args", default="{}", help="JSON kwargs for --op")
    a = ap.parse_args(argv)
    try:
        if a.op:
            result = run_op(a.op, **json.loads(a.args))
            print(json.dumps(result, default=str, indent=2))
        elif a.plan:
            with open(a.plan) as fh:
                steps = json.load(fh)
            print("== run_plan: %d step(s) ==" % len(steps))
            results = run_plan(steps)
            for r in results:
                print("  [OK] %s: %s" % (r["step"], json.dumps(r["result"], default=str)[:200]))
            print("== all %d step(s) OK ==" % len(results))
        else:
            ap.error("give a PLAN.json or --op NAME")
        return 0
    except OpError as exc:
        print("\nFAILED: %s" % exc)
        print("(bug report appended to %s)" % BUG_LOG)
        return 1
