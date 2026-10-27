#!/usr/bin/env bash
# Rotatie van secrets in .env (en waar nodig in MySQL / Keycloak).
#
# HANDLEIDING
#   1. Ga naar de map met de .env van de stack (acc/prod: waar docker-compose staat):
#        cd /pad/naar/stack
#   2. Draai het script, voor alles of voor specifieke keys:
#        /pad/naar/rotatie_password.sh
#        /pad/naar/rotatie_password.sh API_KEY_1 FORMS_APP_KEY
#      Er wordt eerst een backup gemaakt: .env.bak.<timestamp>
#   3. Recreate direct de containers die .env lezen (restart is niet genoeg):
#        docker compose -f docker-compose-<acc|prod>.yml up -d crm forms
#   4. Werk externe afnemers bij (zie meldingen aan het eind van het script).
#   5. Controleer de site en verwijder daarna de .env.bak.* (bevat oude secrets).
#
#   Vereist: draaiende container "crm" (andere naam: CRM_CONTAINER=...), openssl.
#   Andere .env: ENV_FILE=/pad/.env
#
# KEYS
#   DB_PASSWORD, DB_ANALYTICS_PASSWORD     MySQL-wachtwoord van de user + .env
#   API_KEY_1                              CRM API key; forms krijgt hem als CRM_API_KEY
#   FORMS_API_KEY                          forms API key; CRM gebruikt hem, forms krijgt hem als API_KEY_1
#   PATIENT_PORTAL_KEYCLOAK_CLIENT_SECRET  door Keycloak geregenereerd (client patient-app) + .env;
#                                          forms krijgt hem als KEYCLOAK_CLIENT_SECRET
#   CRM_KEYCLOAK_CLIENT_SECRET             door Keycloak geregenereerd (client crm-app) + .env; SSO-login CRM
#   FORMS_APP_KEY                          Laravel APP_KEY van forms; wordt toegevoegd als hij ontbreekt.
#                                          Patiënten worden één keer uitgelogd.
set -euo pipefail

ENV_FILE="${ENV_FILE:-.env}"
CRM_CONTAINER="${CRM_CONTAINER:-crm}"
ALL_KEYS=(DB_PASSWORD DB_ANALYTICS_PASSWORD API_KEY_1 FORMS_API_KEY PATIENT_PORTAL_KEYCLOAK_CLIENT_SECRET CRM_KEYCLOAK_CLIENT_SECRET FORMS_APP_KEY)

if [ $# -gt 0 ]; then KEYS=("$@"); else KEYS=("${ALL_KEYS[@]}"); fi

[ -f "$ENV_FILE" ] || { echo "Geen $ENV_FILE in $(pwd)" >&2; exit 1; }

env_get() { # value of KEY in .env, surrounding quotes stripped
    grep -E "^$1=" "$ENV_FILE" | tail -n1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/; s/^'\''(.*)'\''$/\1/'
}

env_set() { # replace KEY=..., or append it when missing
    local tmp
    tmp="$(mktemp "${ENV_FILE}.XXXX")"
    awk -v k="$1" -v v="$2" 'BEGIN{FS=OFS="="} $1==k{print k"="v; done=1; next} {print} END{if(!done) print k"="v}' "$ENV_FILE" > "$tmp"
    cat "$tmp" > "$ENV_FILE" # keep owner/permissions of the original
    rm -f "$tmp"
    [ "$(env_get "$1")" = "$2" ] || { echo "LET OP: $ENV_FILE niet bijgewerkt. Nieuw $1: $2" >&2; exit 1; }
}

generate() {
    local value
    value="$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "$1")"
    [ "${#value}" -eq "$1" ] || { echo "Genereren mislukt" >&2; exit 1; }
    echo "$value"
}

# ALTER USER runs inside the crm container as the account itself (CURRENT_USER()),
# so it hits exactly the user@host account the app uses; no root credentials needed.
rotate_db_password() {
    local key="$1" user_key="$2" user old new
    user="$(env_get "$user_key")"
    old="$(env_get "$key")"
    new="$(generate 32)"
    [ -n "$user" ] && [ -n "$old" ] || { echo "$key of $user_key ontbreekt in $ENV_FILE" >&2; exit 1; }

    echo "→ $key ($user)"

    # Passwords go via env vars, not argv, so they don't show up in `ps`.
    docker exec -i -e H="$(env_get DB_HOST)" -e P="$(env_get DB_PORT)" -e U="$user" -e OLD="$old" -e NEW="$new" "$CRM_CONTAINER" php -r '
        $dsn = "mysql:host=".getenv("H").";port=".getenv("P");
        $pdo = new PDO($dsn, getenv("U"), getenv("OLD"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("ALTER USER CURRENT_USER() IDENTIFIED BY ".$pdo->quote(getenv("NEW")));
        new PDO($dsn, getenv("U"), getenv("NEW"));
    ' || { echo "MySQL wijzigen mislukt voor $key; $ENV_FILE niet aangepast." >&2; exit 1; }

    env_set "$key" "$new"
    echo "  ok: MySQL en $ENV_FILE bijgewerkt, inloggen met nieuw wachtwoord getest"
}

rotate_env_value() {
    echo "→ $1"
    env_set "$1" "$2"
    echo "  ok: $ENV_FILE bijgewerkt"
}

# Keycloak is the source of truth: let it regenerate the secret, then copy it into .env.
rotate_keycloak_secret() {
    local key="$1" client_id="$2" new
    echo "→ $key ($client_id)"

    new="$(docker exec -i -e CLIENT_ID="$client_id" "$CRM_CONTAINER" php artisan tinker --execute='
        $kc = app(App\Services\Keycloak\KeycloakService::class);
        $token = $kc->getAdminToken();
        $client = $token ? $kc->getClientById(getenv("CLIENT_ID"), $kc->getRealm(), $token) : null;
        $value = $client ? Illuminate\Support\Facades\Http::withToken($token)
            ->post($kc->getInternalBaseUrl()."/admin/realms/".$kc->getRealm()."/clients/".$client["id"]."/client-secret")
            ->json("value") : null;
        echo "NEW_SECRET=", $value, PHP_EOL;
    ' 2>/dev/null | sed -n 's/^NEW_SECRET=//p')"

    [ -n "$new" ] || { echo "Keycloak secret regenereren mislukt voor $client_id; $ENV_FILE niet aangepast." >&2; exit 1; }

    env_set "$key" "$new"
    echo "  ok: secret in Keycloak geregenereerd en in $ENV_FILE gezet"
}

for key in "${KEYS[@]}"; do
    [[ " ${ALL_KEYS[*]} " == *" $key "* ]] || { echo "Onbekende key: $key (ondersteund: ${ALL_KEYS[*]})" >&2; exit 1; }
done

backup="${ENV_FILE}.bak.$(date +%Y%m%d%H%M%S)"
cp -p "$ENV_FILE" "$backup"
echo "Backup: $backup"

for key in "${KEYS[@]}"; do
    case "$key" in
        DB_PASSWORD) rotate_db_password "$key" DB_USERNAME ;;
        DB_ANALYTICS_PASSWORD) rotate_db_password "$key" DB_ANALYTICS_USERNAME ;;
        API_KEY_1 | FORMS_API_KEY) rotate_env_value "$key" "$(generate 40)" ;;
        PATIENT_PORTAL_KEYCLOAK_CLIENT_SECRET) rotate_keycloak_secret "$key" patient-app ;;
        CRM_KEYCLOAK_CLIENT_SECRET) rotate_keycloak_secret "$key" crm-app ;;
        FORMS_APP_KEY) rotate_env_value "$key" "base64:$(openssl rand -base64 32)" ;;
    esac
done

rotated() { [[ " ${KEYS[*]} " == *" $1 "* ]]; }

echo
echo "Klaar. Recreate nu direct de containers die .env lezen (restart is niet genoeg):"
echo "  docker compose -f docker-compose-<acc|prod>.yml up -d crm forms"
if rotated DB_PASSWORD || rotated DB_ANALYTICS_PASSWORD; then
    echo "- DB: bestaande verbindingen blijven werken tot de recreate; nieuwe hebben het nieuwe wachtwoord nodig."
    echo "  Gebruikt Metabase de analytics-user? Werk het wachtwoord bij in Metabase > Admin > Databases."
fi
if rotated API_KEY_1; then
    echo "- API_KEY_1: werk externe aanroepers van de CRM API bij (forms gaat automatisch via CRM_API_KEY)."
fi
if rotated FORMS_API_KEY; then
    echo "- FORMS_API_KEY: werk externe aanroepers van de forms API bij (CRM gaat automatisch)."
fi
if rotated PATIENT_PORTAL_KEYCLOAK_CLIENT_SECRET; then
    echo "- Keycloak: het oude secret werkt al niet meer; patiëntportaal-login faalt tot forms is gerecreate."
fi
if rotated CRM_KEYCLOAK_CLIENT_SECRET; then
    echo "- Keycloak: het oude CRM-secret werkt al niet meer; SSO-login op de CRM faalt tot crm is gerecreate."
fi
if rotated FORMS_APP_KEY; then
    echo "- FORMS_APP_KEY: ingelogde patiënten moeten na de recreate opnieuw inloggen."
fi
echo "Daarna: verwijder $backup (bevat de oude secrets)."
