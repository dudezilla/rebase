# Tournament code lineage — one modular git repo

All tournament data is referenced through a single, self-contained git repository whose
linear history *is* the evolution — original source included. It is built by a
**consistent apparatus** (`assemble_lineage.sh`) that moves every stage's code out of the
original sources and commits it as an ordered, `checkout`-able stack.

## Build (or rebuild) it

```bash
./assemble_lineage.sh [DEST]        # default DEST: ~/tournament-lineage
```

Deterministic: fixed author/committer identity and dates mean re-running reproduces the
**same commit hashes** byte-for-byte. Self-contained: it needs only `git` and the two
source repos already on disk.

## What it references (the commit stack)

| tag          | stage             | source moved in                                          |
|--------------|-------------------|----------------------------------------------------------|
| `original`   | original source   | pristine congruency (`congruency-bugs/vendor/congruency`, the 2006 "old-code") |
| `refactors`  | initial refactors | the resurrected/fixed source at the tournament entry base (`congruency @ 2a2e4f8c142e`) |
| `submission` | this entry        | the evolved tag engine + oracle + showcase (`HEAD:2a2e4f8…/`) |

Each stage is a full-tree commit, so:

```bash
git -C DEST log --oneline
git -C DEST diff original refactors     # the resurrection refactor (what made it run)
git -C DEST diff refactors submission   # my entire contribution
git -C DEST checkout submission          # or original / refactors — any point is live
```

## Why this shape

- **Modular** — one repo, no external dependency; drops anywhere and carries the whole story.
- **Original source included** — stage 1 is the unmodified 2006 code, not a description of it.
- **Consistent apparatus** — the same script produces the same repo every time; the lineage
  is regenerated, never hand-curated.
- **Referenceable** — original → initial refactors → submission are tags/commits you can
  diff and check out, so provenance of every line is git-traceable end to end.

The apparatus lives at the submission root (`assemble_lineage.sh`); it modifies nothing in
the sources — it only reads them and writes the destination repo.
