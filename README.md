# congruencey — the ratchet

`main` is the **ratchet**: a forward-only spine that carries no application source — only
`install.py`. The numbered branches (`b00`, `b01`, … the **cranks**) hold the actual work;
each `turn N` commit is one crank stroke. Per-crank **state** (the app DB) lives on a
dedicated orphan `state` branch, not in the source branches.

## Install & run a crank

```
git clone <repo> && cd congruencey        # lands on main (source-free)
python3 install.py                          # installs the highest crank (bNN)
python3 install.py --branch b03             # a specific crank
```

`install.py` (stdlib only, self-contained) does, per step — recording telemetry and catching
any bug thrown:

1. **checkout** the crank branch in place (one source tree at a time);
2. **provision php** — `file-system-repair/provision_php.py` (static PHP 8, network recipe);
3. **install state** — pulls `state:<crank>/database.tar.xz` from the `state` side-branch and
   extracts it; if the branch has no state for this crank yet, it auto-creates it via
   `checkouts/current/tools/make_state.py` (deterministic re-seed);
4. **stand up** — `checkouts/current/fixes/boot_www.py` (CMS at HTTP 200);
5. **verify** — `tooling/congruencey-tests/verify` (stand-up + bug-catalog + branch-coverage).

Flags: `--refresh-state` (rebuild the crank's state), `--no-verify`, `--return-to-main`.

## State

`python3 checkouts/current/tools/make_state.py [--crank bNN]` re-seeds the DB deterministically
(`state/seed.php` under the provisioned php), tars it (`congruency.sqlite` + `seed.php`) and
commits it to the orphan `state` branch at `<crank>/database.tar.xz` — via git plumbing only,
so the working tree never switches. `checkouts/current/state/STATE.json` is the per-crank state
spec (seed generator, artifact path, side branch, expected tables).

## Telemetry & bugs

Every install/produce step emits to `jazz_telemetry` (component `ratchet`, sink
`~/.jazz/congruency.sqlite`) when available, and any exception is written as a timestamped
Variant-A bug report to the registry's `bug_reports` sink (`file-system-repair/bug_reports.jsonl`)
and opens a mechanical-id ticket. Telemetry is best-effort and never blocks an install.
