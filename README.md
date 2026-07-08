# congruencey — the ratchet

Steven Peterson's 2006 PHP CMS ("congruency"), resurrected on PHP 8 and folded into a
ratchet-managed package. **One branch, `main`**, carries everything: the installer
(`setup.py`) at the root **and** the CMS source + tooling under `checkouts/current/`. A crank is
a **commit + `version-*` tag**, not a branch. Each crank is one self-contained tree — installer +
source + database + configuration object — so there is no separate `state` branch.

Layout under `checkouts/current/`:

| path | holds |
|---|---|
| `congruency/` | the CMS app + its tooling (`tools/`, `fixes/`, `versioning/`, `boot/`, `lib/`, …) |
| `state/` | the crank's database, shipped compressed as `database.tar.xz` (extracted to `congruency.sqlite` on install) |

## Lifecycle (dev / test)
```
git clone <repo> && cd rebase
python3 setup.py install     # checkout newest version + provision php + install db (+ verify)
python3 setup.py up          # bring the CMS up  -> http://0.0.0.0:8899
python3 setup.py down        # take the CMS down
python3 setup.py uninstall   # tear the tree back to the minted crank (purge runtime)
```
No hand-typed version or flags: **`install.json` is the configuration object** — it stamps the
release version and carries the lifecycle params (host/port/no_verify). It is emitted by
instrumentation at mint time and committed into each version tag, so the verbs are
config-object-driven (an orchestrator drives the lifecycle via that one object). `install` per step
records telemetry and files a Variant-A bug report on any unexpected outcome:
1. **checkout** the tag `version-X` (detached — materializes the crank);
2. **provision php** — `checkouts/current/congruency/tools/provision_php.py` (static PHP 8; idempotent);
3. **install state** — extract the `database.tar.xz` that rides in the version commit → `checkouts/current/state/congruency.sqlite`;
4. **verify** — `tooling/congruencey-tests/verify` (stand-up + bug-catalog + branch-coverage).

Overrides (the configuration object always wins by default):
```
python3 setup.py install path/to/install.json
python3 setup.py install --version 4.081 [--no-verify] [--return-to-main]
python3 setup.py up --port 9000
python3 setup.py emit-config      # instrumentation: write ./install.json for the newest version
```
`uninstall` is a **tolerated anomaly** point: if bringing it up dirtied the tree, it files a bug
report and force-recovers the tree to the minted crank.

## How work happens (minting a crank)
```
python3 checkouts/current/congruency/tools/mint_crank.py --patch P.py --name x
```
Each crank is one Python patch, captured **in place** (commit + `version-*` tag), **test-first**
(predictions → `logs/predictions.jsonl`; a refuted prediction is a bug). Before the commit, mint
produces the crank's state and its `install.json`, so the version commit carries **both** the
`database.tar.xz` and the install config. `make_state.py` snapshots the unified database named by
`checkouts/current/state/STATE.json` `source_db`, falling back to the currently-installed db (never
re-fabricating a stub); the tarball is written in-tree and folded into the version commit.

## Telemetry & bugs
Every step emits to `jazz_telemetry` when available; any unexpected outcome writes a Variant-A bug
report to the registry's `bug_reports` sink (`logs/bug_reports.jsonl`) and opens a mechanical-id
ticket. Best-effort — never blocks.

## More docs
`VERSION-NOTES.md` (per-crank notes) · `DEPENDENCIES.md` (runtime deps +
process changes) · `checkouts/current/congruency/ARCHITECTURE.md` (the 2006 CMS internals).
