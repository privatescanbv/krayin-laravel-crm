#!/bin/bash

APP_ENV="$1"

# Command uitvoeren
$ARTISAN migrate:fresh &&
# create realm and capture output to extract the secrets
./sync_keycloak.sh "$APP_ENV" &&
$ARTISAN db:seed &&
$ARTISAN import:users
