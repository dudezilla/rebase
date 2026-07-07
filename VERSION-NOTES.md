# VERSION-NOTES

Per-crank notes. Newest first. A crank = a commit + a `version-*` tag.

---

## version-4.080 — `congruency/` layout repath + db installed in-crank

**Layout (the spec):** `checkouts/current/{congruency, state}` — the CMS app under
`congruency/`, the database in a sibling `state/`. The old flat `checkouts/current/*`
copies were removed.

**Where it runs:** dev server on `0.0.0.0:8899` (`serve.py`, `--port` to override).

**Paths corrected** (app root `checkouts/current` → `checkouts/current/congruency`; but
`state/` is a sibling, so `SOURCE/state` → `dirname(SOURCE)/state`):

Functional (app/tooling-breaking):

| File:line | Was | Now |
|---|---|---|
| `checkouts/current/congruency/tools/serve.py:36` | `join(SOURCE,"state")` | `join(dirname(SOURCE),"state")` |
| `checkouts/current/congruency/tools/make_state.py:31` | `join(SOURCE,"state")` | `join(dirname(SOURCE),"state")` |
| `checkouts/current/congruency/tools/make_state.py` (build_tarball) | seed-fabricates a stub when `source_db` gone | re-snapshots the **installed** `state/congruency.sqlite` (db installed every crank; never a stub) |
| `checkouts/current/congruency/boot/configure.php:42` | `CONGRUENCY_SQLITE = ABS_PATH.'state'` | `dirname(rtrim(ABS_PATH,SLASH)).'/state'` |
| `checkouts/current/congruency/tools/provision_php.py:156` | git-add `checkouts/current/tools/...` | `.../congruency/tools/...` |
| `tooling/congruencey-tests/verify:35` | `SOURCE = MONO/checkouts/current` | `MONO/checkouts/current/congruency` |
| `tooling/coverage/branch_test.php:10` | `$SRC = .../checkouts/current` | `.../checkouts/current/congruency` |
| `tooling/coverage/branch_test2.php:6` | `$SRC = .../checkouts/current` | `.../checkouts/current/congruency` |
| `install.py:240` (main/ratchet) | `checkouts/current/tools/provision_php.py` | `.../congruency/tools/provision_php.py` |
| `install.py:277` (main/ratchet) | `checkouts/current/tools/serve.py` | `.../congruency/tools/serve.py` |

Comment/docstring/usage strings also corrected: `serve.py:6,34`, `make_state.py:14,29-30`,
`mint_crank.py:10,15,33`, `verify:11`, `ENTRY_POINT.py:29`.

Left unchanged (already correct — `state/` is a sibling): `checkouts/current/state/*`,
`install.py` `_state_spec`/`do_state`, `mint_crank` `step_state` artifact, root-relative
`registry.json paths.php` and `STATE.json seed/artifact`.

Dormant, flagged not edited (one-shot applied patch scripts, not in the runtime path):
`fixes/install_config_loader.py:142`, `fixes/install_prod_seed.py:23`,
`fixes/install_state_db.py:22`, `fixes/prove_git_store.py:33`,
`fixes/state_rides_in_crank.py:16`.

**Verify:** 3 passed, 0 failed. **DB:** 23-table unified db installed in
`checkouts/current/state/`.
