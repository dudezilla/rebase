# Tournament package — one serializable object

A single git monorepo that contains an entire code-evolution tournament and is designed
to hold future ones. Serialize the whole thing to one file:

    git bundle create tournament.bundle --all      # (already emitted alongside this repo)

and re-hydrate it anywhere, offline:

    git clone tournament.bundle tournament && cd tournament

## What's inside

    tournaments/congruency-2026/     one tournament (add siblings for future tournaments)
      brief/        the mission
      lineage/      code lineage as a bundle: original -> initial refactors -> submission
      submission/   the evolved entry, checked out for browsing
      apparatus/    harness / tests / bugs / coverage / pwdriver — each a self-contained bundle
      database/     deterministic seed + SQL dump (rebuild the DB, no live binaries shipped)
      docs/         architecture, provenance, layout
      MANIFEST.txt  inventory + the source commit each piece came from

Nested repos are their OWN bundles (full history, self-contained). The database is a
reproducible seed/dump, not a binary. The PHP interpreter is an INSTALL note, not a blob —
see INSTALL.md. Everything needed to rebuild and run is content or instruction.

## Future tournaments

Drop another directory under `tournaments/` (e.g. `tournaments/congruency-2027/`) with the
same shape and re-bundle. One object, many tournaments, full history preserved.
