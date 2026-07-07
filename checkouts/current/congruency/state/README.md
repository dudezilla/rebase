# state — carried in the crank

State is **not** a side branch. Each crank (a commit + `version-*` tag) carries its own
database as an in-tree artifact:

    checkouts/current/state/database.tar.xz

* **produced by** `tools/make_state.py` — snapshots the big unified database named by
  `STATE.json` `source_db` (default `~/.jazz/congruency.sqlite`) via sqlite's WAL-safe
  online backup, and writes the tarball here.
* **captured by** `tools/mint_crank.py` — runs make_state BEFORE commit, so `add -A`
  folds `database.tar.xz` into the version commit.
* **installed by** `install.py` — the version checkout materializes the tarball; `do_state`
  extracts it in place into `congruency.sqlite` (gitignored).

The retired `state` branch and `state-<version>` tags no longer exist.
