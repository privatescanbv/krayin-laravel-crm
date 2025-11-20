#!/bin/bash
# Create Realm for Keycloak and update .env with the new secrets
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

replace_keycloak_secrets_block() {
  local secret="$1"
  local forms_secret="$2"
  local realm_public_key="$3"

  # Generate replacement block
  local replacement_block="KEYCLOAK_CLIENT_SECRET=${secret}
KEYCLOAK_CLIENT_SECRET_FORMS=${forms_secret}
KEYCLOAK_REALM_PUBLIC_KEY=${realm_public_key}"

  # If prod, only print, don't replace
  if [[ "$APP_ENV" == "prod" ]]; then
    echo "Production mode: printing values (not updating .env file):"
    echo "$replacement_block"
    return 0
  fi

  # Check if placeholder exists (case-insensitive)
  if ! grep -qi "<replace_keycload_realm_secrets>" .env 2>/dev/null; then
    echo "Placeholder <replace_keycload_realm_secrets> not found in .env file. Skipping replacement." >&2
    return 0
  fi

  # Create temp file with replacement block (without placeholder, so it won't match on next run)
  local temp_replacement=$(mktemp)
  echo "$replacement_block" > "$temp_replacement"

  # Use awk to replace the entire block in one go
  awk -v replacement_file="$temp_replacement" '
  BEGIN {
    IGNORECASE = 1
    in_block = 0
    # Read replacement block from file
    while ((getline line < replacement_file) > 0) {
      replacement = replacement (replacement ? "\n" : "") line
    }
    close(replacement_file)
  }
  /<replace_keycload_realm_secrets>/ {
    # Skip placeholder line and print replacement (without placeholder)
    printf "%s\n", replacement
    in_block = 1
    next
  }
  in_block && /^KEYCLOAK_(CLIENT_SECRET|CLIENT_SECRET_FORMS|REALM_PUBLIC_KEY)=/ {
    if (/^KEYCLOAK_REALM_PUBLIC_KEY=/) in_block = 0
    next
  }
  { print }
  ' .env > .env.tmp && mv .env.tmp .env

  # Clean up temp file
  rm -f "$temp_replacement"
}

# Kies artisan command
if [[ "$APP_ENV" == "prod" ]]; then
    ARTISAN="php artisan"
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

# Replace the block in .env file
replace_keycloak_secrets_block "$secret" "$forms_secret" "$realm_public_key" &&
$ARTISAN cache:clear &&
$ARTISAN config:clear &&
$ARTISAN optimize:clear
