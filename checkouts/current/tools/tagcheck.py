#!/usr/bin/env python3
"""tagcheck.py — a render-harness for every invocator tag (first increment of #46).

Discovers the tag list from the live gallery (?page=tags), renders each tag standalone
(?page=tags&tag=NAME), and asserts HTTP 200 with no PHP error in the output. Prints a
pass/fail table + JSON, exit non-zero if any tag fails. This is the "rendered result"
half of the tag-test harness; predicted results (as `bet` tickets) come next.

    python3 tools/tagcheck.py                 # against http://127.0.0.1:8899
    python3 tools/tagcheck.py --base URL --report out.json
"""
import argparse, json, re, sys, time, urllib.request, urllib.error

ERR = ("Fatal error", "Parse error", "Uncaught", "PHP Fatal", "Stack trace",
       "error:", "cy-form-error",
       # non-fatal PHP diagnostics too (bug #108: a null-deref Warning slipped past a fatals-only check)
       "Warning:", "Deprecated:", "Notice:", "Trying to access", "array offset on null")


def get(url):
    try:
        with urllib.request.urlopen(url, timeout=15) as r:
            return r.status, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")
    except Exception as e:  # noqa: BLE001
        return None, "%r" % e


def tags(base):
    st, body = get(base + "/?page=tags")
    return sorted(set(re.findall(r"[?&]tag=([A-Za-z0-9_]+)", body))) if st == 200 else []


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="http://127.0.0.1:8899")
    ap.add_argument("--report", default="")
    a = ap.parse_args()
    base = a.base.rstrip("/")
    names = tags(base)
    results, fails = [], []
    for n in names:
        st, body = get("%s/?page=tags&tag=%s" % (base, n))
        hit = [m for m in ERR if m in body]
        ok = (st == 200 and not hit)
        results.append({"tag": n, "status": st, "ok": ok, "errors": hit, "bytes": len(body)})
        if not ok:
            fails.append(n)

    print("=== tagcheck — %s ===" % base)
    print("%d tags, %d pass, %d FAIL\n" % (len(results), len(results) - len(fails), len(fails)))
    for r in results:
        print("  %-5s %-3s  %-22s %s" % ("ok" if r["ok"] else "FAIL", r["status"], r["tag"],
                                         (", ".join(r["errors"]) if r["errors"] else "")))
    if a.report:
        json.dump({"base": base, "results": results, "fails": fails}, open(a.report, "w"), indent=2)
        print("\nJSON: %s" % a.report)
    return 1 if fails else 0


if __name__ == "__main__":
    sys.exit(main())
