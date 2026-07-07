#!/usr/bin/env python3
"""crawl.py — a background web-spider for the congruency site.

BFS over every link on every page starting from the site root: follows all INTERNAL
links, records HTTP status, flags BROKEN links (a ?page=X whose X is not a real Document,
or any non-200), lists EXTERNAL links, tracks which page each link was found on, and
writes a report (human text to stdout + JSON to --report).

The site returns HTTP 200 for unknown pages (it falls back to the 'invalid' Document), so
"broken" is decided by the page allowlist, fetched live from the REST API (?api=Documents)
— not by status code alone.

    python3 tools/crawl.py                         # crawl http://127.0.0.1:8899
    python3 tools/crawl.py --base http://127.0.0.1:8899 --report /tmp/crawl.json
    python3 tools/crawl.py --check-external        # also HEAD-check external links (needs network)
"""
import argparse, json, re, sys, time
import urllib.parse, urllib.request, urllib.error
from collections import deque, defaultdict

HREF = re.compile(r'href=[\'"]([^\'"]+)[\'"]', re.I)


def fetch(url, timeout=10, method="GET"):
    try:
        req = urllib.request.Request(url, method=method)
        with urllib.request.urlopen(req, timeout=timeout) as r:
            body = r.read().decode("utf-8", "replace") if method == "GET" else ""
            return r.status, body, r.headers.get("Content-Type", "")
    except urllib.error.HTTPError as e:
        return e.code, "", ""
    except Exception as e:  # noqa: BLE001
        return None, "%r" % e, ""


def valid_pages(base):
    """The page allowlist, straight from the REST API."""
    st, body, _ = fetch(base + "/?api=Documents&per=500")
    ids = set()
    if st == 200:
        try:
            for row in json.loads(body).get("rows", []):
                ids.add(row.get("DocumentID"))
        except Exception:  # noqa: BLE001
            pass
    return ids


def crawl(base, check_external):
    host = urllib.parse.urlparse(base).netloc
    pages = valid_pages(base)
    seen, ext_seen = set(), {}
    refs = defaultdict(set)                    # link -> set(pages it was found on)
    result = {}                                # internal url -> {status, broken, reason, nlinks}
    broken = []
    q = deque(["/?page=catalog", "/"])

    while q:
        path = q.popleft()
        url = urllib.parse.urljoin(base + "/", path)
        pr = urllib.parse.urlparse(url)
        if pr.netloc and pr.netloc != host:                         # external
            if url not in ext_seen:
                ext_seen[url] = fetch(url, timeout=5, method="HEAD")[0] if check_external else "not-checked"
            continue
        norm = pr.path + (("?" + pr.query) if pr.query else "")
        if norm in seen:
            continue
        seen.add(norm)

        status, body, ctype = fetch(url)
        qs = urllib.parse.parse_qs(pr.query)
        page_param = qs.get("page", [None])[0]
        broken_flag, reason = False, None
        if status != 200:
            broken_flag, reason = True, "HTTP %s" % status
        elif page_param is not None and pages and page_param not in pages:
            broken_flag, reason = True, "no such page '%s' (falls back to invalid)" % page_param

        found = HREF.findall(body) if "html" in ctype.lower() or body.lstrip().startswith("<") else []
        result[norm] = {"status": status, "broken": broken_flag, "reason": reason, "nlinks": len(found)}
        if broken_flag:
            broken.append({"url": norm, "reason": reason, "found_on": sorted(refs.get(norm, []))})

        for href in found:
            if href.startswith(("mailto:", "javascript:", "#")):
                continue
            tgt = urllib.parse.urljoin(url, href)
            tp = urllib.parse.urlparse(tgt)
            tnorm = tp.path + (("?" + tp.query) if tp.query else "")
            if not tp.netloc or tp.netloc == host:
                refs[tnorm].add(norm)
                tq = urllib.parse.parse_qs(tp.query)
                if "file" in tq or "doc" in tq:      # the self-hosting archive: hundreds of content-addressed
                    continue                          # views (?page=source&file=<hash> / ?page=docs&doc=<hash>) — record, don't descend
                if tnorm not in seen:
                    q.append(tnorm)
            else:
                refs[tgt].add(norm)
                if tgt not in ext_seen:
                    q.append(tgt)

    return {
        "base": base, "valid_pages": sorted(pages),
        "internal": result, "broken": broken,
        "external": [{"url": u, "status": s, "found_on": sorted(refs.get(u, []))} for u, s in sorted(ext_seen.items())],
    }


def report(data):
    r = data["internal"]
    ok = sum(1 for v in r.values() if not v["broken"])
    bad = len(data["broken"])
    lines = []
    lines.append("=== congruency crawl report — %s ===" % data["base"])
    lines.append("crawled %d internal URLs  (ok: %d, broken: %d)  |  valid pages: %d  |  external links: %d"
                 % (len(r), ok, bad, len(data["valid_pages"]), len(data["external"])))
    lines.append("")
    lines.append("BROKEN LINKS (%d):" % bad)
    if not data["broken"]:
        lines.append("  none ✓")
    for b in data["broken"]:
        lines.append("  %-40s  %s" % (b["url"], b["reason"]))
        lines.append("      found on: %s" % ", ".join(b["found_on"]))
    lines.append("")
    lines.append("EXTERNAL LINKS (%d):" % len(data["external"]))
    for e in data["external"]:
        lines.append("  [%s] %s" % (e["status"], e["url"]))
        lines.append("      found on: %s" % ", ".join(e["found_on"]))
    lines.append("")
    lines.append("INTERNAL URLS (%d):" % len(r))
    for u in sorted(r):
        v = r[u]
        flag = "BROKEN" if v["broken"] else "ok"
        lines.append("  %-6s %-3s  links=%-3s  %s" % (flag, v["status"], v["nlinks"], u))
    return "\n".join(lines)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="http://127.0.0.1:8899")
    ap.add_argument("--report", default="")
    ap.add_argument("--check-external", action="store_true")
    a = ap.parse_args()
    t0 = time.time()
    data = crawl(a.base.rstrip("/"), a.check_external)
    data["elapsed_sec"] = round(time.time() - t0, 2)
    print(report(data))
    print("\n(%d URLs in %.1fs)" % (len(data["internal"]), data["elapsed_sec"]))
    if a.report:
        with open(a.report, "w") as f:
            json.dump(data, f, indent=2)
        print("JSON report: %s" % a.report)
    return 1 if data["broken"] else 0


if __name__ == "__main__":
    sys.exit(main())
