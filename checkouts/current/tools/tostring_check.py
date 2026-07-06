#!/usr/bin/env python3
"""tostring_check.py — flush the #60 class: __toString() that can return non-string.

PHP 8 fatals with a TypeError when __toString() returns null. This scans lib/ (+ invocators/)
for __toString methods and flags any that:
  (a) have NO return statement (falls off the end -> null), or
  (b) return a bare variable/property that was initialised to NULL in the body (e.g. $x=NULL; ... return $x;).
Returns of string literals, interpolations, concatenations, casts, or `?? ''` are safe.

    python3 tools/tostring_check.py        # report; exit non-zero if any risky __toString
"""
import os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DIRS = ["lib", "invocators"]


def bodies(src):
    """yield (start_line, body) for each __toString method via brace matching."""
    for m in re.finditer(r'function\s+__toString\s*\([^)]*\)\s*\{', src):
        i = m.end() - 1                      # at the opening {
        depth, j = 0, i
        while j < len(src):
            if src[j] == '{': depth += 1
            elif src[j] == '}':
                depth -= 1
                if depth == 0: break
            j += 1
        yield src[:m.start()].count('\n') + 1, src[i + 1:j]


def risky(body):
    rets = re.findall(r'return\s+([^;]+);', body)
    if not rets:
        return "no return statement (falls through -> null)"
    for r in rets:
        r = r.strip()
        m = re.fullmatch(r'\$([A-Za-z_]\w*)((?:->\w+)*)', r)   # a bare $var or $this->prop
        if m:
            var = m.group(1)
            # nullable if that local var was initialised to NULL, or it's any object property
            if re.search(r'\$%s\s*=\s*NULL\s*;' % re.escape(var), body, re.I) or m.group(2):
                return "returns bare nullable `%s`" % r
    return None


def main():
    flagged = []
    for d in DIRS:
        for base, _, files in os.walk(os.path.join(ROOT, d)):
            for fn in files:
                if not fn.endswith(".php"):
                    continue
                p = os.path.join(base, fn)
                src = open(p, errors="replace").read()
                for line, body in bodies(src):
                    why = risky(body)
                    if why:
                        flagged.append((os.path.relpath(p, ROOT), line, why))
    print("%d risky __toString method(s)%s" % (len(flagged), ":" if flagged else " — clean ✓"))
    for rel, line, why in sorted(flagged):
        print("  %s:%d  %s" % (rel, line, why))
    return 1 if flagged else 0


if __name__ == "__main__":
    sys.exit(main())
