# congruencey — tournament submission

Evolved from the 2006 **congruency** PHP content-management system (`PHP - content
management`). This folder is a code-evolution tournament entry: a self-contained
snapshot of branch `order-logging` @ commit `2a2e4f8c142e…`, evolved into a minimal,
reliable, exhaustively-tested tagging system.

See **`submission_00.txt`** for the entry's location relative to the main codebase and
the full protocol details.

## What this is

At the core is a **custom tagging system** — a parser that scans content for
`<<<Name(arg)(arg)>>>` tags and renders it. The entry evolves that core, guided entirely
by an oracle (test-first), against seven goals.

## Run the oracle

```bash
cd tests/parser
../../../congruencey-harness/php/php run.php    # exit 0 iff all assertions pass
```

One command. **No database, no web server, zero JavaScript.** Prints
`parser corpus: N/N assertions passed`.

## Layout

```
2a2e4f8c142e…/            this submission (snapshot of the branch @ entry)
├── lib/ invocators/ …    the original CMS under test (unmodified)
├── submission_00.txt     where this entry sits vs. the main codebase
└── tests/parser/         ALL evolved code + tested data
    ├── run.php           the single oracle (4481 green assertions)
    ├── manifest.json     self-describing dataset graph (38 datasets)
    ├── *.php             render / eval / scan / compute / expand / store / flow / …
    ├── generate_*.php    ground-truth generators (re-run + reproduced by the oracle)
    ├── *-fixtures.json   captured/authored test data
    └── README.md         architecture + per-goal coverage map (start here for detail)
```

## The seven mission goals (all implemented, test-first)

1. **One state → four formats at the edge** — XML/HTML/JSON/YAML, single + collection,
   **bidirectional**, plus a 4×4 ingest-any/emit-any interoperability matrix. Format is a
   registry key, never baked into logic.
2. **No magic strings** — every key resolves through maps (tag registry, op-table, handler
   registry); dispatch precedence is **op > handler > template**.
3. **Self-configuring + self-healing** — reads its own `manifest.json`, reconciles the
   description against reality, and regenerates corrupt datasets (`Corpus::heal`); a
   reproducibility guard re-runs every generator.
4. **Turing-complete evaluation core** — 100+ recursive programs (factorial, Fibonacci,
   Ackermann, gcd, popcount, isqrt, Collatz, mutual/accumulator recursion) + 100+ AST
   expressions. `<<<add(2)(3)>>>` and `<<<if(c)(t)(e)>>>` drive computation through the
   **real parser**.
5. **Zero JavaScript** — server-side `form → server → next-form` state machine
   (reachability-verified); a real tag asserted to emit no `<script>`.
6. **Git as the primary store** — file store, git-versioned store (history *is* the
   database), and a content-addressed blob store (sha1 + dedup).
7. **Modernized dated conventions, guided by tests** — killed the `Tag_Parser`
   dynamic-property deprecation; `scan.php` validated against the real
   `Tag_Wrapper::identify_tag` via reflection.

## Final state

**38 datasets · 4481 green oracle assertions · 104 commits since entry.** Oracle green
every round — zero missed commits, zero oracle failures. Ground truth is captured from
the live code; authored expectations are locked as regression baselines.

Detailed architecture and the suite-to-goal map: **`tests/parser/README.md`**.
