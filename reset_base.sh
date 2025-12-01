#!/bin/bash

APP_ENV="$1"
# Kies artisan command
if [[ "$APP_ENV" == "prod" ]]; then
    ARTISAN="php artisan"
else
    ARTISAN="./vendor/bin/sail artisan"
fi

# Command uitvoeren
$ARTISAN migrate:fresh &&
# create realm and capture output to extract the secrets
./create-realm.sh "$APP_ENV" &&
$ARTISAN db:seed &&
$ARTISAN import:users &&
$ARTISAN keycloak:sync-users
