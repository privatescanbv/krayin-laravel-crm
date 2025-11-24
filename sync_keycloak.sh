#!/bin/bash

APP_ENV="$1"

extract_value() {
  local key="$1"
  local description="$2"
  # Extract line after the "---" separator for cleaner parsing
  local line
  line=$(printf '%s\n' "$realm_output" | awk '/^---$/{flag=1; next} flag && /^'"${key}"'=/{print; exit}' || printf '%s\n' "$realm_output" | grep "^${key}=" | head -1)
  if [ -z "$line" ]; then
    echo "Failed to extract ${description} from keycloak:create-realm output" >&2
    echo "Output was:" >&2
    echo "$realm_output" >&2
    exit 1
  fi
  # Extract value after =, trim whitespace, and remove any trailing newlines
  printf '%s' "$line" | sed -E "s/^${key}=//; s/^[[:space:]]*//; s/[[:space:]]*$//" | tr -d '\n'
}

update_env_var() {
  local key="$1"
  local value="$2"
  # Remove existing entry if present
  if grep -q "^${key}=" .env 2>/dev/null; then
    sed -i '' "/^${key}=/d" .env
  fi
  # Use printf to safely write the key=value pair, properly escaping special characters
  printf '%s=%s\n' "${key}" "${value}" >> .env
}

# Kies artisan command
if [[ "$APP_ENV" == "prod" ]]; then
    ARTISAN="docker exec crm php artisan"
else
    ARTISAN="./vendor/bin/sail artisan"
fi

echo "Running keycloak sync using: $ARTISAN"

# Command uitvoeren
# create realm and capture output to extract the secrets
realm_output=$($ARTISAN keycloak:create-realm)

# extract values (ensure they are properly quoted)
secret="$(extract_value "KEYCLOAK_CLIENT_SECRET" "KEYCLOAK_CLIENT_SECRET")"
forms_secret="$(extract_value "FORMS_KEYCLOAK_CLIENT_SECRET" "FORMS_KEYCLOAK_CLIENT_SECRET")"
realm_public_key="$(extract_value "KEYCLOAK_REALM_PUBLIC_KEY" "KEYCLOAK_REALM_PUBLIC_KEY")"

# update .env values
update_env_var "KEYCLOAK_CLIENT_SECRET" "$secret" &&
update_env_var "KEYCLOAK_CLIENT_SECRET_FORMS" "$forms_secret" &&
update_env_var "KEYCLOAK_REALM_PUBLIC_KEY" "$realm_public_key" &&
$ARTISAN cache:clear &&
$ARTISAN config:clear &&
$ARTISAN optimize:clear
