#!/bin/bash

set -e

DIRS="
.claude
.cursor
.docker
.github
resources
app
database
config
tests
routes
packages
lang
"

for dir in $DIRS; do
  if [ -d "$dir" ]; then
    echo "➕ Adding directory: $dir"
    git add "$dir"
  else
    echo "⚠️  Directory not found, skipping: $dir"
  fi
done

echo "✅ Done."
