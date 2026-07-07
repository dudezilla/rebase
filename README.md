# congruencey — the ratchet

Steven Peterson's 2006 PHP CMS ("congruency"), resurrected on PHP 8 and folded into a ratchet-managed
package. The repo is **three single branches** — a crank is a **commit + version tag**, not a branch:

| branch | holds |
|---|---|
| **main** (this one) | the **ratchet** — the installers only: `install.py` (dev/test) + `deploy.py` (production). No app source. |
| **source** | the CMS + tooling. A crank = one commit + a `version-4.05x` tag, in place (`mint_crank.py`). |
| **state** | the compressed dev DB (a single `database.tar.xz`), tagged `state-<version>` to match a source version. |

## Install a version (dev / test)
```
git clone <repo> && cd congruencey        # lands on main (source-free)
python3 install.py --version 4.071          # required: which source version to stand up
```
`install.py` (stdlib-only, self-contained) per step — recording telemetry, catching any bug thrown:
1. **checkout** the source tag `version-X` (detached — materializes the source);
2. **provision php** — `checkouts/current/tools/provision_php.py` (static PHP 8; idempotent);
3. **install state** — the matching demo DB from the `state` branch (`state-<version>`, else newest ≤ X);
4. **stand up + verify** — `tooling/congruencey-tests/verify` (stand-up + bug-catalog + branch-coverage).

Flags: `--no-verify`, `--return-to-main`.

## Deploy a version (production)
```
python3 deploy.py --target /srv/site --version 4.071
```
Exports the app to the target with a JSON config (`install.json`) + a **fresh production stub DB**
(intro + catalog + 404, current styling, no demo content), creates the target if absent, and boots
config-driven — verifying the stub is up (recorded as predictions). See **DEPLOY.md**.

## How work happens (on `source`)
```
python3 checkouts/current/tools/mint_crank.py --patch P.py --name x   # a crank = commit + version tag
```
Each crank is one Python patch, captured **in place** on `source`, **test-first** (predictions recorded
to `logs/predictions.jsonl`; a refuted prediction is a bug), then state-snapshotted and verified before
it lands. The `version-4.05x` tags are the crank detents; `make_state.py --version X` snapshots the DB
to the `state` branch (single `database.tar.xz`) via git plumbing, tagging `state-<version>`.

## Telemetry & bugs
Every step emits to `jazz_telemetry` (`~/.jazz/congruency.sqlite`) when available; any unexpected
outcome writes a Variant-A bug report to the registry's `bug_reports` sink (`logs/bug_reports.jsonl`)
and opens a mechanical-id ticket. Best-effort — never blocks.

## More docs
`source:README.md` (structure) · `source:DEPLOY.md` (production) · `source:DEPENDENCIES.md` (runtime deps
+ process changes) · `source:checkouts/current/ARCHITECTURE.md` (the 2006 CMS internals).
