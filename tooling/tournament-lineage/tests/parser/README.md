# Parser corpus — the tournament oracle

A self-contained, deterministic test corpus for the congruency custom tagging
system. **No DB, no web server, zero JavaScript.** One command:

```bash
../../../../congruency-harness/php/php run.php   # exit 0 iff every assertion passes
```

`run.php` is the single oracle. It self-configures from `manifest.json`, exercises
the real parser plus a small evolved engine built to the seven mission goals, and
prints `parser corpus: N/N assertions passed`.

## Architecture (the evolved parser)

```
content ──scan──▶ [tags] ──parse──▶ {name, args}
                                      │
                    ┌─────────────────┼──────────────────┐
                 compute            expand              handler
             (eval op-table)   (template map)     (live invocator tag)
                    └─────────────────┼──────────────────┘
                                      ▼
                              render at the edge
                       XML · HTML · JSON · YAML  (bidirectional)
                                      ▼
                          persist: files / git (versioned)
```

- **scan** (`scan.php`) — `Tag_Wrapper::identify_tag` mirror; finds `<<<Name(a)(b)>>>` in text.
- **parse** — the real `Tag_Parser` / `TagArguments` under test.
- **compute** (`eval.php`, `compute.php`) — Turing-complete op-table with named recursion; a tag can drive computation.
- **expand / render** (`expand.php`, `render_document.php`) — recursive substitution with a depth guard.
- **render edge** (`render.php`, `collection.php`) — one internal state → four formats and back; format is a registry key, never baked in.
- **persist** (`store.php`, `gitstore.php`) — canonical state to files; git history as the primary store.
- **forms** (`flow.php`) — server-side form→server→next-form state machine, zero JS.
- **self-config / self-heal** (`corpus.php`) — reads its own `manifest.json`; reconciles description vs. reality; regenerates corrupt datasets.

## Mission-goal coverage

| Goal | Where |
|------|-------|
| 1 — same state → XML/HTML/JSON/YAML at the edge | `render.php`, `collection.php`; Suites B, J, R, T, W (interop matrix) |
| 2 — resolve every key through maps, no magic strings | `registry.php`, op-table, handler registry; Suites E, RI, P, HD |
| 3 — reads own description, self-configures + self-heals | `corpus.php`, `manifest.json`; Suites K, X (reproducibility), HL (heal) |
| 4 — Turing-complete evaluation core | `eval.php`; Suites G, H (recursive programs), P2, Z (error contract) |
| 5 — zero JavaScript, server-side forms | `flow.php`; Suites L, FR (reachability), BR (no `<script>` in a real tag) |
| 6 — git as primary store, SQL for mutable forms | `store.php`, `gitstore.php`; Suites M, U (versioned history) |
| 7 — modernize dated conventions, test-guided | `Tag_Parser` `$tag_invocation` declared; Suite D (no dynamic-property deprecation) |

## Datasets

Every `*.json` here is registered in `manifest.json` (Suite K enforces no orphans /
no missing). Generator-backed datasets (`generate_*.php`) are re-run by Suite X and
must reproduce their committed content.

Ground truth is **captured from the live code**, never hand-guessed; authored
expectations (formats, programs) are locked as regression baselines.

## Scale (mature state)

**38 datasets · 4,300+ green assertions · one command, exit 0.** Highlights:
- parser ground truth: hundreds of captured cases (arg-counts 0–40, name shapes, whitespace, edge/malformed, scanner↔parser divergence matrix).
- four-format edge: single-state + collection renderers, both **bidirectional**, plus a 4×4 **interoperability matrix** (ingest-any → emit-any, lossless).
- Turing core: 90+ recursive programs (factorial, fib, Ackermann, gcd, popcount, isqrt, Collatz, mutual + accumulator recursion) and 100+ AST expressions.
- computational tags: `<<<op(a)(b)>>>` and `<<<if(c)(t)(e)>>>` drive the eval core through the **real parser**.
- real code under test: `scan.php` validated against `Tag_Wrapper::identify_tag` (reflection); live `TestTagA` recursion and `BugReport` (zero-JS invariant) exercised.
- persistence: file store, git-versioned store (history = database), content-addressed blob store (sha1, dedup).
- self-config/self-heal: manifest-driven suites, reproducibility guard (every generator re-runs), `Corpus::heal()` regenerates corrupt datasets.

Dispatch precedence in the unified renderer: **op > handler > template** (Suite PRC).

## Facts this corpus pins (captured, not assumed)
- Delimiters `<<<` … `>>>`; a tag body is `Name(arg)(arg)…`.
- Arguments come back **reversed** (stack order); `pop()`-draining recovers source order.
- `Show()` yields one empty-string argument; bare `Foo` yields zero.
- The content **scanner** name regex is `[a-zA-Z_]+` (no digits) while the **parser**
  identifier allows digits — a deliberate, tested divergence (Suite S).
- `etc/Constants.php` permits underscores in names/args; `www/Constants.php` does not (Suite Y).
