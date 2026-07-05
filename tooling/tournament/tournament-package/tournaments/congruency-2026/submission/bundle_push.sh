#!/usr/bin/env bash
#
# bundle_push.sh — "push" every project repo to a local git bundle.
#
# A git bundle is not a native `git push` target (bundles are read-only transport),
# so pushing == regenerating the bundle from the repo's current refs. This captures
# ALL branches and tags (including the version-N tags) of every source repo and its
# submodules into ~/bundles/<name>.bundle, and registers a `bundle` remote on each so
# they can be fetched/cloned back offline. Use this as the local stand-in for the
# external remote until a real push path exists.
#
# Usage:  ./bundle_push.sh [BUNDLE_DIR]        (default: ~/bundles)
#
set -euo pipefail
ROOT="${TOURNAMENT_ROOT:-/home/notificationsforsteven}"
BDIR="${1:-$ROOT/bundles}"
mkdir -p "$BDIR"

REPOS=(congruency congruency-harness congruency-tests congruency-bugs coverage pwdriver)

push_one() {  # $1 = repo path, $2 = bundle name
  local repo="$1" name="$2" out="$BDIR/$2.bundle"
  git -C "$repo" bundle create "$out" --all >/dev/null 2>&1
  git -C "$repo" remote remove bundle >/dev/null 2>&1 || true
  git -C "$repo" remote add bundle "$out"
  local head tags
  head=$(git -C "$repo" rev-parse --short HEAD)
  tags=$(git -C "$repo" bundle list-heads "$out" | grep -c 'refs/tags/' || true)
  printf "  %-34s -> %-26s HEAD=%s tags=%s (%s)\n" \
    "$name" "$(basename "$out")" "$head" "$tags" "$(du -h "$out" | cut -f1)"
}

echo ">> pushing repos to local bundles at: $BDIR"
for r in "${REPOS[@]}"; do
  [ -d "$ROOT/$r/.git" ] && push_one "$ROOT/$r" "$r"
done
# the pinned original submodule, too
SUB="$ROOT/congruency-bugs/vendor/congruency"
[ -e "$SUB/.git" ] && push_one "$SUB" "vendor-congruency"

echo ">> verifying every bundle re-opens cleanly:"
ok=0; bad=0
for b in "$BDIR"/*.bundle; do
  if git bundle verify "$b" >/dev/null 2>&1; then ok=$((ok+1)); else bad=$((bad+1)); echo "   BAD: $b"; fi
done
echo ">> $ok bundle(s) valid, $bad bad. Fetch back with: git -C <repo> fetch bundle"
