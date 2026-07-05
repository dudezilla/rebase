# Bugs found while building the versioning / bundle / checkout apparatus

Standing rule: **any bug we trip across gets recorded here** — symptom, root cause, fix,
status. This log is versioned with the tooling and propagates between checkouts.

Format: `BUG-Vnn | date | component | status`.

---

## BUG-V01 | 2026-07-04 | gitutil.submodule_status | FIXED
**Symptom:** a clean submodule was reported with state `?` (and `verify_git_state.py`
showed `vendor/congruencey[?]`) instead of `in-sync`.
**Root cause:** `Git.run()` returned `stdout.strip()`. `git submodule status` encodes the
sync flag in a **leading space** on each line; the global strip ate the first line's leading
space, so `line[0]` became the first sha digit, not the flag.
**Fix:** added `strip=` to `Git.run`/`Git.query` (default True) and call
`submodule_status` with `strip=False`; parse the flag only when `line[0] in " +-U"`.

## BUG-V02 | 2026-07-04 | checkout.py submodule wiring | FIXED
**Symptom:** `checkout.py` aborted with `fatal: transport 'file' not allowed` when running
`git submodule update` from a local `file://` bundle.
**Root cause:** git hardening (CVE-2022-39253) disables the `file` transport for submodule
clones by default.
**Fix:** run submodule update as
`git -c protocol.file.allow=always submodule update -- <path>` — scoped to that one
command, not global config.

## BUG-V03 | 2026-07-04 | git bundle create (usage gotcha) | NOTED
**Symptom:** `git -C <repo> bundle create bundles/x.bundle --all` →
`fatal: Unable to create '.../<repo>/bundles/x.bundle.lock': No such file or directory`.
**Root cause:** with `-C <repo>`, a **relative** output path resolves inside the repo, not
the caller's cwd.
**Fix / guard:** always pass an **absolute** output path for `bundle create`
(`bundle_push.sh` already does; ad-hoc one-liners must too).

## BUG-V04 | 2026-07-04 | ops/refresh_bundles.py | FIXED
**Symptom:** `refresh_bundles` raised `bundle failed verify` on a bundle that was actually
valid; caught by `run_op` and auto-recorded to `bug_reports.jsonl`.
**Root cause:** the op checked `git bundle verify`'s **stdout** for `"is okay"`, but that
message is written to **stderr** and validity is signalled by the **exit code**.
**Fix:** verify by exit code — `git.run(["bundle","verify",out], check=True)` (raises on a
bad bundle) — never parse stdout. First bug the runner pattern caught in the wild.

## BUG-V05 | 2026-07-04 | ops/*.py (portability) | FIXED
**Symptom:** on a clone at any path other than `/home/notificationsforsteven`, the
multi-repo ops target the original absolute layout and fail; the toolset is not portable.
**Root cause:** several ops hardcoded absolute defaults instead of deriving them relative to
`__file__`:
* `commit_release` / `refresh_bundles` / `verify_bundles` / `propagate_version` defaulted `root`/`bundles_dir` to `/home/notificationsforsteven[/bundles]`;
* `generate_manifest` / `commit_release` hardcoded the entry hash `2a2e4f8c142e…`.
**Fix:** added `versioning/paths.py` — derives `repo_root`/`project_root`/`entry_hash`/
`bundles_dir` from `__file__` + `git rev-parse --show-toplevel`. The ops now default their
paths to `None` and resolve via `paths.py` (still overridable). Verified in-container: the
derived values equal the previous absolute ones, so behaviour is unchanged but portable.
**Status:** FIXED (minimal). The full config-driven vision is RD-01.

---

## RD-01 | 2026-07-04 | REDESIGN — config-driven pathing & arguments | OPEN
**What:** all pathing must be **configured via a python-loaded JSON**, and every path (and
every argument) must reach the tooling **programmatically** from that config — not from
`__file__` derivation, not from per-op defaults, not hard-coded.
**Why:** `paths.py` (BUG-V05 fix) makes the tools portable, but paths/args are still resolved
ad-hoc inside each op. A single source of truth (e.g. `versioning/config.json` loaded by a
`config.py`) that every op reads means: one place to point the toolchain at any layout, no
duplicated resolution, and callers can override any path/arg by editing JSON rather than code.
**Scope (proposed):**
* `config.py` loads/validates a JSON config (repo/project roots, bundles dir, entry hash,
  module map, identities, remote) and exposes it to every op;
* ops take all paths + arguments from the config (with explicit call-site overrides winning);
* `ENTRY_POINT.py` seeds/locates the config relative to itself.
**Status:** OPEN — NOT started (deferred; BUG-V05 unblocks the push for now).

---

_Append new entries below as they are found (next id: BUG-V06)._
