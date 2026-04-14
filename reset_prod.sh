#!/bin/bash

# Ask for import limit (default -1 means no limit)
read -p "Enter import limit (-1 = no limit) [ -1 ]: " IMPORT_LIMIT
IMPORT_LIMIT=${IMPORT_LIMIT:--1}

if [ "$IMPORT_LIMIT" = "-1" ]; then
    # import all
    PERSON_LIMIT_ARG=""
    LEAD_LIMIT_ARG=""
else
    PERSON_LIMIT_ARG="--limit=${IMPORT_LIMIT}"
    LEAD_LIMIT_ARG="--limit=${IMPORT_LIMIT}"
fi

LOG_FILE="storage/logs/import-$(date +%Y%m%d-%H%M%S).log"
echo "Logging to ${LOG_FILE}"

run() {
    echo "==> $*" | tee -a "${LOG_FILE}"
    "$@" 2>&1 | tee -a "${LOG_FILE}"
    return "${PIPESTATUS[0]}"
}

run ./reset_base.sh prod &&
run php artisan import:persons ${PERSON_LIMIT_ARG} &&
run php artisan import:leads ${LEAD_LIMIT_ARG} &&
run php artisan import:orders &&
run php artisan import:email-attachment-files &&
run php artisan planning:create-test-data &&
run php artisan import:send-report &&
run php artisan duplicates:refresh-cache --clear
