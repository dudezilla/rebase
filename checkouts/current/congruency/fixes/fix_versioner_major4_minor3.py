#!/usr/bin/env python3
"""fix_versioner_major4_minor3.py — corrective artifact (ratchet fix).

Phase 3 of THE LOOP: prove the versioner BEFORE standup. The shipped
versioning/version_source.py::compute_next_version pins the base major to 2 and
formats the minor with 4 digits ("%d.%04d"). The spec requires:

    major pinned 4, minor 3-digit zero-padded, first minor 50  ->  start "4.050",
    +1 minor per patch.

This fix rewrites the two format sites in compute_next_version, then PROVES the
result by driving the patched function with a stub git (no real git, no other
repos touched): no tags -> "4.050"; one tag version-4.050 -> "4.051".

Permanent, python-only, self-verifying. Placed in fixes/ off the source git and
recorded in fixes/index.json. Idempotent: re-running is a no-op once patched.
"""
import importlib.util
import json
import os
import sys
import time
import traceback

HERE = os.path.dirname(os.path.abspath(__file__))                 # checkouts/current/fixes
SOURCE = os.path.dirname(HERE)                                    # checkouts/current
VS = os.path.join(SOURCE, "versioning", "version_source.py")
INDEX = os.path.join(HERE, "index.json")

OLD_A = 'return "%d.%04d" % (major if major is not None else 2, 1)'
NEW_A = 'return "%d.%03d" % (major if major is not None else 4, 50)'
OLD_B = 'return "%d.%04d" % (major, nxt)'
NEW_B = 'return "%d.%03d" % (major, nxt)'


class StubGit:
    """Minimal stand-in: only compute_next_version's git.query(['tag','-l',...]) is used."""
    def __init__(self, tags):
        self._tags = tags

    def query(self, args, repo=None):
        if args[:2] == ["tag", "-l"]:
            return "\n".join(self._tags)
        return ""


def patch():
    src = open(VS).read()
    changed = False
    if OLD_A in src:
        src = src.replace(OLD_A, NEW_A); changed = True
    if OLD_B in src:
        src = src.replace(OLD_B, NEW_B); changed = True
    if changed:
        with open(VS, "w") as fh:
            fh.write(src)
    already = (NEW_A in src and NEW_B in src)
    if not already:
        raise RuntimeError("version_source.py not in expected shape; format sites missing")
    return changed


def load_compute():
    # import version_source with its own dir on sys.path (it imports gitutil, verify_git_state)
    vdir = os.path.dirname(VS)
    if vdir not in sys.path:
        sys.path.insert(0, vdir)
    spec = importlib.util.spec_from_file_location("version_source_patched", VS)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod.compute_next_version


def prove(compute):
    first = compute(StubGit([]), SOURCE)                       # no versions yet
    assert first == "4.050", "start version wrong: got %r, want '4.050'" % first
    nxt = compute(StubGit(["version-4.050"]), SOURCE)          # one patch applied
    assert nxt == "4.051", "increment wrong: got %r, want '4.051'" % nxt
    padded = compute(StubGit(["version-4.050", "version-4.051"]), SOURCE)
    assert padded == "4.052", "second increment wrong: got %r" % padded
    return {"start": first, "after_one_patch": nxt, "after_two": padded}


def record(changed, proof):
    entry = {
        "fix": os.path.basename(__file__),
        "target": os.path.relpath(VS, SOURCE),
        "purpose": "versioner: major pinned 4, minor 3-digit padded, start 4.050, +1/patch",
        "applied_change": changed,
        "proof": proof,
        "recorded": time.strftime("%Y-%m-%dT%H:%M:%S"),
    }
    idx = []
    if os.path.isfile(INDEX):
        idx = json.load(open(INDEX))
    idx = [e for e in idx if e.get("fix") != entry["fix"]] + [entry]
    with open(INDEX, "w") as fh:
        json.dump(idx, fh, indent=2)
    return entry


def main():
    changed = patch()
    proof = prove(load_compute())
    entry = record(changed, proof)
    print(json.dumps({"ok": True, "changed": changed, "proof": proof,
                      "recorded_in": os.path.relpath(INDEX, SOURCE)}, indent=2))
    return entry


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # noqa: BLE001 — a failed fix is a telemetry ticket
        tb = traceback.format_exc()
        ticket = {
            "filename": os.path.basename(__file__),
            "function": (traceback.extract_tb(exc.__traceback__)[-1].name
                         if exc.__traceback__ else "?"),
            "time-of-occurance": time.strftime("%Y-%m-%dT%H:%M:%S"),
            "methods-to-reproduce": "python3 %s" % os.path.basename(__file__),
            "possible-cause": "%s: %s" % (type(exc).__name__, exc),
            "traceback": tb.strip().splitlines()[-6:],
            "note": "ratchet fix: prove versioner",
        }
        bugs = os.path.join(os.path.dirname(os.path.dirname(SOURCE)),
                            "logs", "bug_reports.jsonl")
        try:
            with open(bugs, "a") as fh:
                fh.write(json.dumps(ticket) + "\n")
        except OSError:
            pass
        print("EXCEPTION — ticket filed", file=sys.stderr)
        raise
