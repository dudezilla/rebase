#!/usr/bin/env bash
#
# assemble_lineage.sh — the consistent apparatus.
#
# Moves ALL tournament code from the original source into ONE modular git repo as an
# ordered, referenceable commit stack. Every stage is a full-tree commit, so any point
# is `git checkout`-able and each transition is a `git diff`:
#
#   1. original source     pristine congruency (the 2006 "old-code")
#   2. initial refactors   the resurrected / fixed source at the tournament entry base
#   3. submission          the evolved tag engine + oracle + showcase (this entry)
#
# Deterministic (fixed identity + dates), self-contained, and re-runnable: running it
# again reproduces byte-for-byte the same three commits. All tournament data is then
# referenced through this single repo — original source included.
#
# Usage:  ./assemble_lineage.sh [DEST_REPO]      (default: ~/tournament-lineage)
#
set -euo pipefail

ROOT="${TOURNAMENT_ROOT:-/home/notificationsforsteven}"
ENTRY_HASH="2a2e4f8c142eafaac061fe1cebc3c93cf27849b3"

ORIGINAL_REPO="$ROOT/congruency-bugs/vendor/congruency"   # pristine original (submodule @ old-code)
SOURCE_REPO="$ROOT/congruency"                             # tournament source (entry base + my commits)
DEST="${1:-$ROOT/tournament-lineage}"

git --version >/dev/null || { echo "git is required"; exit 127; }

# -- lay down a full tree into DEST (wiping the previous stage, keeping .git) ----------
reset_tree() {
  find "$DEST" -mindepth 1 -maxdepth 1 ! -name .git -exec rm -rf {} +
}
commit_stage() {  # $1 = ISO date, $2 = subject, $3.. = body lines
  local date="$1" subject="$2"; shift 2
  git -C "$DEST" add -A
  GIT_AUTHOR_DATE="$date" GIT_COMMITTER_DATE="$date" \
  git -C "$DEST" -c user.name='tournament-apparatus' -c user.email='apparatus@congruency.local' \
    commit -q --allow-empty -m "$subject" ${1:+-m "$*"}
}

echo ">> assembling lineage repo at: $DEST"
rm -rf "$DEST"
mkdir -p "$DEST"
git -C "$DEST" init -q

# --- stage 1: ORIGINAL SOURCE --------------------------------------------------------
echo ">> [1/3] original source  <- $ORIGINAL_REPO (pristine congruency)"
reset_tree
git -C "$ORIGINAL_REPO" archive HEAD | tar -x -C "$DEST"
commit_stage '2006-01-01T00:00:00' \
  'original source: pristine congruency (2006 old-code)' \
  'The unmodified CMS as received. Install.txt: "this version does not execute."'

# --- stage 2: INITIAL REFACTORS ------------------------------------------------------
echo ">> [2/3] initial refactors <- $SOURCE_REPO @ $ENTRY_HASH (resurrected source)"
reset_tree
git -C "$SOURCE_REPO" archive "$ENTRY_HASH" | tar -x -C "$DEST"
commit_stage '2026-07-01T00:00:00' \
  'initial refactors: resurrected source at the tournament entry base' \
  "Branch order-logging @ ${ENTRY_HASH:0:12}. The fixes that make the CMS execute on PHP 8 (harness, constants, the 15 catalogued bug fixes). Diff vs. stage 1 = the resurrection refactor."

# --- stage 3: SUBMISSION -------------------------------------------------------------
echo ">> [3/3] submission        <- $SOURCE_REPO HEAD:$ENTRY_HASH/ (evolved entry)"
reset_tree
git -C "$SOURCE_REPO" archive "HEAD:$ENTRY_HASH/" | tar -x -C "$DEST"
commit_stage '2026-07-04T00:00:00' \
  'submission: evolved tag engine + oracle + showcase' \
  'The tournament entry. tests/parser/ = the self-configuring oracle (38 datasets, 4400+ green assertions) over the evolved scan->parse->compute/expand->4-format-render->git-persist pipeline; showcase/ = the zero-JS front-end rendered by that engine. Diff vs. stage 2 = my entire contribution.'

# --- report --------------------------------------------------------------------------
echo
echo ">> lineage assembled — the modular repo now references all tournament data:"
git -C "$DEST" log --stat --oneline | grep -E '^[0-9a-f]{7} |files? changed' | sed 's/^/   /'
echo
echo ">> stage tags for easy reference:"
git -C "$DEST" tag -f original  HEAD~2 >/dev/null 2>&1 || true
git -C "$DEST" tag -f refactors HEAD~1 >/dev/null 2>&1 || true
git -C "$DEST" tag -f submission HEAD   >/dev/null 2>&1 || true
git -C "$DEST" tag | sed 's/^/   tag: /'
echo
echo ">> inspect:  git -C $DEST log --oneline"
echo ">>           git -C $DEST diff original refactors     # the resurrection refactor"
echo ">>           git -C $DEST diff refactors submission   # my contribution"
