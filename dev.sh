#!/bin/bash
# Gebruik: ./dev.sh        -> start clinic-portal, crm en privateforms
#          ./dev.sh stop   -> docker compose down voor alle drie
#
# Elk project draait in een subshell: faalt er één (bv. de Vite-check van privateforms,
# die met `exit 1` stopt), dan gaan de andere gewoon door en staat het resultaat onderaan.
# Volgorde: privateforms als laatste, omdat zijn Vite-check (https://patientdev.local...)
# pas slaagt als de andere stacks draaien.

CRM="$(cd "$(dirname "$0")" && pwd)"
PROJECTS=("$CRM/../../clinic-portal" "$CRM" "$CRM/../../privateforms")
FAILED=()

for dir in "${PROJECTS[@]}"; do
  name="$(basename "$(cd "$dir" && pwd)")"
  echo "=== $name ==="
  if [ "$1" = "stop" ]; then
    (cd "$dir" && docker compose down) || FAILED+=("$name")
  else
    (cd "$dir" && ./start-dev.sh) || FAILED+=("$name")
  fi
done

if [ ${#FAILED[@]} -gt 0 ]; then
  echo "❌ Mislukt: ${FAILED[*]}"
  exit 1
fi
echo "✅ Alles gestart"
