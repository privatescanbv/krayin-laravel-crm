#!/bin/bash

APP_ENV="$1"

# Kies artisan command
if [[ "$APP_ENV" == "prod" ]]; then
    ARTISAN="php artisan"
else
    ARTISAN="./vendor/bin/sail artisan"
fi

echo "Running migrate:fresh using: $ARTISAN"

# Command uitvoeren
$ARTISAN migrate:fresh &&
# create realm and capture output to extract the client secret
realm_output=$($ARTISAN keycloak:create-realm)
# try to extract "KEYCLOAK_CLIENT_SECRET=..." line from output
secret_line=$(printf '%s\n' "$realm_output" | grep 'KEYCLOAK_CLIENT_SECRET=' || true)
if [ -z "$secret_line" ]; then
  echo "Failed to extract KEYCLOAK_CLIENT_SECRET from keycloak:create-realm output"
  echo "Output was:"
  echo "$realm_output"
  exit 1
fi
# extract value after '='
secret=$(printf '%s\n' "$secret_line" | sed -E 's/.*KEYCLOAK_CLIENT_SECRET=//; s/^[[:space:]]*//; s/[[:space:]]*$//')
# update .env: remove existing key then append new one
if grep -q '^KEYCLOAK_CLIENT_SECRET=' .env; then
  sed -i '' '/^KEYCLOAK_CLIENT_SECRET=/d' .env
fi
echo "KEYCLOAK_CLIENT_SECRET=${secret}" >> .env &&
$ARTISAN db:seed &&
$ARTISAN import:users


