# bugs/

A **file-drift catalog** — NOT a code-bug tracker. When context drifted, copies of the software
appeared at random locations across the tree; this folder tracks them by content hash so the
duplication/divergence is visible (nothing is moved or deleted).

| file | what |
|---|---|
| `drift_report.json` | content-addressed drift map (`git hash-object` per file). `duplicates` = identical content in >1 location; `version_drift` = one filename with multiple distinct contents. Last scan: ~5163 files, 413 unique blobs, 399 duplicated across locations, 39 filenames with multiple versions. |
| `note-for-claude` | the rationale: find drifted copies, track them via git hashes, catalogue here. |
| `invocators/` | a quarantined drift sample (a stray copy caught during the sweep). |

## How this differs from the other "bug" surfaces (don't confuse them)
| surface | purpose |
|---|---|
| **`bugs/` (here)** | duplicate / divergent **files** across the tree (drift), by content hash. |
| `logs/bug_reports.jsonl` | runtime **Variant-A bug reports** (any tool, on an unexpected outcome). Sink named by `registry.bug_reports`. |
| jazz_telemetry tickets | the live **ticket store** (mechanical ids, `~/.jazz/congruency.sqlite`) — open/close ratchet + audit tickets. |
| `checkouts/current/fixes/index.json` | the **ratchet ledger** — applied fixes/patches (turns). |
| `tooling/congruencey-bugs/bugs.json` | the **CMS's own 15-bug catalog** (the 2006 defects, with repros). |
