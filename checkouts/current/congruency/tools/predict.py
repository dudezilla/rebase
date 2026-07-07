#!/usr/bin/env python3
"""predict.py — the prediction ledger: record PREDICTION vs ACTUAL, verdict CONFIRMED / REFUTED.

Test-first over the ratchet: state what you EXPECT before the change, run it, then resolve with the
ACTUAL. A REFUTED prediction *is* a bug (opens a jazz ticket, component=deploy). Records to
`logs/predictions.jsonl` (git-ignored) and best-effort jazz telemetry. Registry-gated; Variant-A bug
report on exception.

    from predict import predict, resolve, check
    pid = predict("deploy creates DB at cfg path", expected=True)
    #  ... run the thing ...
    resolve(pid, actual=os.path.isfile(db))                 # -> "CONFIRMED" / "REFUTED"
    check("home page returns 200", expected=200, actual=status)   # one-shot predict+resolve
"""
import json
import os
import sys
import time
import traceback
from datetime import datetime

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
    path = find_registry()
    reg = json.load(open(path))
    reg["__root__"] = os.path.dirname(path)
    return reg


def _sink(reg):
    rel = reg.get("predictions", "logs/predictions.jsonl")
    return os.path.join(reg["__root__"], rel)


def _jazz(reg):
    try:
        pkgs = os.path.dirname(os.path.dirname(reg["__root__"]))   # .../packages
        cand = os.path.join(pkgs, "jazz_telemetry")
        if cand not in sys.path:
            sys.path.insert(0, cand)
        from jazz_telemetry import telemetry, open_ticket
        return telemetry("deploy"), open_ticket
    except Exception:  # noqa: BLE001
        return None, None


def bug_report(reg, exc, tb):
    root = (reg or {}).get("__root__") or os.path.dirname(os.path.dirname(os.path.dirname(HERE)))
    path = os.path.join(root, (reg or {}).get("bug_reports", "logs/bug_reports.jsonl"))
    frames = traceback.extract_tb(exc.__traceback__)
    last = frames[-1] if frames else None
    entry = {"filename": os.path.basename(__file__), "function": last.name if last else "?",
             "time-of-occurance": datetime.now().isoformat(),
             "methods-to-reproduce": "python3 tools/predict.py",
             "possible-cause": "%s: %s" % (type(exc).__name__, exc),
             "traceback": tb.strip().splitlines()[-6:], "note": "predict.py"}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "a") as fh:
            fh.write(json.dumps(entry) + "\n")
    except OSError:
        pass


def _read(sink):
    if not os.path.isfile(sink):
        return []
    out = []
    for ln in open(sink):
        ln = ln.strip()
        if ln:
            try:
                out.append(json.loads(ln))
            except ValueError:
                pass
    return out


def _append(sink, rec):
    os.makedirs(os.path.dirname(sink), exist_ok=True)
    with open(sink, "a") as fh:
        fh.write(json.dumps(rec) + "\n")


def predict(statement, expected, **meta):
    """Record a prediction (BEFORE the change). Returns a mechanical id."""
    try:
        reg = load_registry()
        sink = _sink(reg)
        pid = max([e.get("id", 0) for e in _read(sink) if e.get("kind") == "predict"], default=0) + 1
        _append(sink, {"kind": "predict", "id": pid, "ts": time.strftime("%Y-%m-%dT%H:%M:%S"),
                       "statement": statement, "expected": expected, "meta": meta, "verdict": "PENDING"})
        T, _ = _jazz(reg)
        if T:
            T.emit("predict", status="pending", id=pid, statement=str(statement)[:120])
        return pid
    except Exception as exc:  # noqa: BLE001
        bug_report(None, exc, traceback.format_exc())
        raise


def resolve(pid, actual, open_bug=True, **meta):
    """Resolve a prediction with the ACTUAL. Returns 'CONFIRMED'/'REFUTED'; REFUTED opens a jazz bug."""
    try:
        reg = load_registry()
        sink = _sink(reg)
        pred = next((e for e in _read(sink) if e.get("kind") == "predict" and e.get("id") == pid), None)
        if pred is None:
            raise ValueError("no prediction with id %s" % pid)
        verdict = "CONFIRMED" if actual == pred["expected"] else "REFUTED"
        _append(sink, {"kind": "resolve", "id": pid, "ts": time.strftime("%Y-%m-%dT%H:%M:%S"),
                       "statement": pred["statement"], "expected": pred["expected"],
                       "actual": actual, "verdict": verdict, "meta": meta})
        T, open_ticket = _jazz(reg)
        if T:
            T.emit("predict", status=verdict.lower(), id=pid, statement=str(pred["statement"])[:120])
        if verdict == "REFUTED" and open_bug and open_ticket:
            try:
                open_ticket("prediction REFUTED: %s" % str(pred["statement"])[:120], component="deploy",
                            severity="high", kind="bug",
                            expected=repr(pred["expected"])[:200], actual=repr(actual)[:200])
            except Exception:  # noqa: BLE001
                pass
        return verdict
    except Exception as exc:  # noqa: BLE001
        bug_report(None, exc, traceback.format_exc())
        raise


def check(statement, expected, actual, open_bug=True, **meta):
    """One-shot predict+resolve (when you already have the actual). Returns the verdict."""
    return resolve(predict(statement, expected, **meta), actual, open_bug=open_bug, **meta)


if __name__ == "__main__":
    # tiny CLI: python3 predict.py "statement" <expected> <actual>
    a = sys.argv[1:]
    if len(a) == 3:
        print(check(a[0], a[1], a[2]))
    else:
        print("usage: predict.py \"statement\" <expected> <actual>", file=sys.stderr)
        sys.exit(2)
