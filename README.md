# congruencey — the ratchet

Steven Peterson's 2006 PHP CMS ("congruency"), resurrected on PHP 8 and folded into a
ratchet-managed package. **One branch, `main`**, carries everything: the installers
(`install.py`, `deploy.py`) at the root **and** the CMS source + tooling under
`checkouts/current/`. A crank is a **commit + `version-*` tag**, not a branch. Each crank
carries its own database, so there is no separate `state` branch.

Layout under `checkouts/current/`:

| path | holds |
|---|---|
| `congruency/` | the CMS app + its tooling (`tools/`, `fixes/`, `versioning/`, `boot/`, `lib/`, …) |
| `state/` | the crank's database, shipped compressed as `database.tar.xz` (extracted to `congruency.sqlite` on install) |

## Install a version (dev / test)
```
git clone <repo> && cd rebase     # lands on main
python3 install.py                # zero-arg: auto-resolves the newest version-* tag
```
No hand-typed version or flags: the version + arguments ride in each version's committed
`install.json` (emitted by instrumentation at mint time). `install.py` reads it and per step —
recording telemetry, catching any bug thrown:
1. **checkout** the tag `version-X` (detached — materializes the source);
2. **provision php** — `checkouts/current/congruency/tools/provision_php.py` (static PHP 8; idempotent);
3. **install state** — extract the `database.tar.xz` that rides in the version commit → `checkouts/current/state/congruency.sqlite`;
4. **stand up + verify** — `tooling/congruencey-tests/verify` (stand-up + bug-catalog + branch-coverage).

Override the auto-resolution with a config file or flag:
```
python3 install.py path/to/install.json
python3 install.py --version 4.080 [--no-verify] [--return-to-main]
python3 install.py --emit-config          # instrumentation: write ./install.json for the newest version
```
Dev server: `python3 checkouts/current/congruency/tools/serve.py` → `http://0.0.0.0:8899` (`--port` to change).

## Deploy a version (production)
```
python3 deploy.py --target /srv/site --version 4.080
```
Exports the app to the target with a JSON config (`install.json`) + a **fresh production stub DB**
(intro + catalog + 404, current styling, no demo content), creates the target if absent, and boots
config-driven — verifying the stub is up (recorded as predictions). See **DEPLOY.md**.

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
`VERSION-NOTES.md` (per-crank notes) · `DEPLOY.md` (production) · `DEPENDENCIES.md` (runtime deps +
process changes) · `checkouts/current/congruency/ARCHITECTURE.md` (the 2006 CMS internals).
