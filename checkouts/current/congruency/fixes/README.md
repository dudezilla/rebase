# checkouts/current/fixes/

The **ratchet ledger** and the crank patches. Each patch is ONE self-contained python script that
makes ONE change, following the note-for-claude contract: registry-gated, self-verifying, upserts an
entry into `index.json` on success, and files a Variant-A bug report on any exception.

| item | what |
|---|---|
| `index.json` | the append-only **ratchet ledger** (dedup by `fix`): every applied fix/patch + purpose + timestamp. |
| `examples/turn_example.py` | the **patch template** — copy it, put your one change in `main()`, mint it. |
| turns 1–6 (`fix_versioner_major4_minor3`, `prove_git_store`, `repath_to_new_tree`, `install_state_db`, `boot_www`) | the original "ratchet link" fixes that stood the CMS up. |
| crank patches (`fix_make_state_determinism`, `fix_provision_php_idempotent`, `migrate_provision_php`, `doc_*`) | later cranks, each captured as a `version-4.05x`. |

## How a crank uses this dir
`checkouts/current/tools/mint_crank.py --patch P.py --name x` copies `P.py` here as `fixes/x.py`,
runs it (the one change), then **commits + tags** `version-4.05x` on `source` in place (no branch),
snapshots state, and verifies. Every step records a bug event on any unexpected outcome.
