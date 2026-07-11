#!/usr/bin/env bash

# Merge the latest laravel/installer master into this fork, locally.
#
# Usage: bash scripts/sync-upstream.sh
#
# The CI equivalent (.github/workflows/upstream-sync.yml) runs weekly and opens
# a pull request. Use this script when you want to sync immediately or resolve
# a conflict the workflow reported.

set -euo pipefail

cd "$(dirname "$0")/.."

if ! git remote get-url upstream >/dev/null 2>&1; then
    git remote add upstream https://github.com/laravel/installer.git
fi

if [ -n "$(git status --porcelain)" ]; then
    echo "Working tree is not clean. Commit or stash your changes first." >&2
    exit 1
fi

git fetch upstream master

BEHIND=$(git rev-list --count HEAD..upstream/master)

if [ "$BEHIND" = "0" ]; then
    echo "Already up to date with laravel/installer master."
    exit 0
fi

echo "Fork is $BEHIND commit(s) behind upstream. Merging..."

if ! git merge upstream/master --no-edit; then
    cat >&2 <<'EOF'

Merge conflicts. Our customizations live in:
  - src/WebCommand.php, src/Web/, public/   (web installer)
  - docs/, scripts/                          (documentation + automation)
  - bin/laravel                              (command registration)

Resolve the conflicts, then run:
  composer docs:update && composer docs:check
  vendor/bin/phpstan analyse
  git commit
EOF
    exit 1
fi

composer install --quiet --no-interaction
composer docs:update
composer docs:check
vendor/bin/phpstan analyse --no-progress

if [ -n "$(git status --porcelain)" ]; then
    git add docs/reference
    git commit -m "Sync command documentation after upstream merge"
fi

echo ""
echo "Merged $BEHIND upstream commit(s). Review with 'git log --oneline -10', then push."
