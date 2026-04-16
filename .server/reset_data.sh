#!/bin/bash

# Run met:
# nohup bash reset_data.sh > reset_data.log 2>&1 &

set -e

echo "=== Start reset_data.sh $(date) ==="

run_crm() {
  echo "=== [crm] start ==="
  docker exec crm php artisan optimize:clear
  docker exec crm bash ./reset_prod.sh
  echo "=== [crm] klaar ==="
}

run_forms() {
  echo "=== [forms] start ==="
  docker exec forms php artisan optimize:clear
  docker exec forms php artisan migrate:fresh --seed
  echo "=== [forms] klaar ==="
}

run_crm &
PID1=$!

run_forms &
PID2=$!

wait $PID1
STATUS1=$?

wait $PID2
STATUS2=$?

if [ $STATUS1 -ne 0 ] || [ $STATUS2 -ne 0 ]; then
  echo "=== FOUT opgetreden ==="
  exit 1
fi

echo "=== Klaar $(date) ==="
