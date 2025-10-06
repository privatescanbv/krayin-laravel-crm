#!/bin/bash
set -euo pipefail

DB_NAME="scrm_privatesuite9"
BACKUP_DIR="$HOME"
FILENAME="$(date +%F).sql.gz"   # Alleen datum, bijv. 2025-10-06.sql.gz
FULL_PATH="${BACKUP_DIR}/${FILENAME}"

echo "Start backup voor database: $DB_NAME"

# Dump maken en comprimeren
if mysqldump -u mark -p \
  --single-transaction --quick --routines --triggers --events \
  --default-character-set=utf8mb4 --hex-blob --tz-utc \
  "$DB_NAME" | gzip > "$FULL_PATH"; then
    echo "✅ Backup voltooid: $FULL_PATH"

    # Alleen uitvoeren als dump geslaagd is
    scp "$FULL_PATH" mbulthuis@178.251.28.76:/home/mbulthuis/ && \
      echo "📤 Bestand succesvol gekopieerd naar server."
else
    echo "❌ Backup mislukt, bestand niet gekopieerd."
fi
