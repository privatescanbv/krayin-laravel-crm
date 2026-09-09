#!/usr/bin/env bash
# List (and optionally delete) remote branches whose commits are all already
# in origin/development, either via merge or cherry-pick (patch-id match).
# main and development itself are always excluded.
#
# Usage:
#   ./scripts/prune-merged-branches.sh          # list only (dry run)
#   ./scripts/prune-merged-branches.sh --delete  # list, ask y/N, then delete

set -euo pipefail

BASE=origin/development
DELETE=${1:-}

git fetch --prune origin

# patch-ids of every commit already on development
dev_patch_ids=$(git log "$BASE" --format=%H | while read -r c; do
    git show "$c" | git patch-id
done | awk '{print $1}' | sort -u)

deletable=()

for ref in $(git branch -r | sed 's/^[* ] //' | grep -v -E 'origin/(main|development|HEAD)'); do
    branch=${ref#origin/}
    commits=$(git rev-list "$BASE..$ref" 2>/dev/null || true)

    all_known=true
    for c in $commits; do
        pid=$(git show "$c" | git patch-id | awk '{print $1}')
        if [ -z "$pid" ] || ! grep -qx "$pid" <<<"$dev_patch_ids"; then
            all_known=false
            break
        fi
    done

    if $all_known; then
        deletable+=("$branch")
    fi
done

if [ ${#deletable[@]} -eq 0 ]; then
    echo "Geen remote branches gevonden die veilig verwijderd kunnen worden."
    exit 0
fi

echo "Branches die al (deels via cherry-pick) in development zitten:"
printf '  %s\n' "${deletable[@]}"

if [ "$DELETE" != "--delete" ]; then
    echo
    echo "Dry run. Run met --delete om te verwijderen (met bevestiging)."
    exit 0
fi

echo
read -r -p "Verwijder deze ${#deletable[@]} branches op origin? [y/N] " confirm
if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
    for branch in "${deletable[@]}"; do
        git push origin --delete "$branch"
    done
else
    echo "Geannuleerd."
fi
