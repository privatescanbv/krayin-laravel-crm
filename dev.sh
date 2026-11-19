#!/bin/bash
# Gebruik: ./dev.sh        -> start privateforms, crm en clinic-portal
#          ./dev.sh stop   -> docker compose down voor alle drie
set -e

CRM="$(cd "$(dirname "$0")" && pwd)"
PROJECTS=("$CRM/../../privateforms" "$CRM" "$CRM/../../clinic-portal")

for dir in "${PROJECTS[@]}"; do
  echo "=== $(basename "$(cd "$dir" && pwd)") ==="
  cd "$dir"
  if [ "$1" = "stop" ]; then
    docker compose down
  else
    ./start-dev.sh
  fi
done
