#!/usr/bin/env bash
# Build wp-dynamic-location-menus.zip from tracked files only, excluding development files.
# The zip asset is named after the GitHub repo. The folder inside is dynamic-location-menus,
# which is the installed plugin directory.
# Used by .github/workflows/release.yml; run locally with `bash bin/build-zip.sh` to inspect the package.
set -euo pipefail

cd "$(dirname "$0")/.."
rm -rf build wp-dynamic-location-menus.zip
mkdir -p build/dynamic-location-menus

# Tracked files only (never local settings, vendor/, or untracked scratch files), minus dev-only paths.
git ls-files -z \
  | grep -zvE '^(\.github/|\.claude/|tests/|bin/|docs/|\.gitignore$|\.gitattributes$|AGENTS\.md$|CLAUDE\.md$|OWNERSHIP\.md$|CONTRIBUTING\.md$|INTENT\.md$|PLAN\.md$|composer\.(json|lock)$|phpcs\.xml\.dist$|phpunit\.xml\.dist$)' \
  | xargs -0 -I{} cp --parents {} build/dynamic-location-menus/

( cd build && zip -qr9 ../wp-dynamic-location-menus.zip dynamic-location-menus )
unzip -l wp-dynamic-location-menus.zip
