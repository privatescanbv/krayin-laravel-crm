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

php artisan migrate:fresh --seed &&
php artisan import:users &&
php artisan keycloak:sync-users &&
php artisan import:persons ${PERSON_LIMIT_ARG} &&
php artisan import:leads ${LEAD_LIMIT_ARG} &&
php artisan import:email-attachment-files &&
php artisan planning:create-test-data
#php artisan import:send-report &&



