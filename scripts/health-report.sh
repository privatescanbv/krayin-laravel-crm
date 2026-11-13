#!/bin/bash
# Toont queue-checks uit /health/report van prod of acc, over HTTPS.
# Token: HEALTH_REPORT_TOKEN_PROD / HEALTH_REPORT_TOKEN_ACC uit .env (alleen die key gelezen, .env niet
# gesourced), of uit env-variabele HEALTH_REPORT_TOKEN. Nooit als argument (history/ps).
# Usage: scripts/health-report.sh <prod|acc> [base-url] [--full]
#   prod: https://newcrm.privatescan.nl, acc: https://newcrm.dev.privatescan.nl (base-url overschrijft)
#   --full: volledig report i.p.v. alleen status + queue-checks
# Roept eerst /health/ready aan (zoals de hostingprovider), daarna /health/report.
# Vereist dat je IP door de Traefik-allowlist komt (zelfde als crm-whitelist).

set -euo pipefail

cd "$(dirname "$0")/.."

ENVIRONMENT="${1:-}"
BASE_URL=""
FILTER='.status, ((.checks // [])[] | select(.name|startswith("queue")) | {name,status,observed_value})'
for arg in "${@:2}"; do
  case "$arg" in
    --full) FILTER='.' ;;
    *) BASE_URL="$arg" ;;
  esac
done

case "$ENVIRONMENT" in
  prod) : "${BASE_URL:=https://newcrm.privatescan.nl}" ;;
  acc) : "${BASE_URL:=https://newcrm.dev.privatescan.nl}" ;;
  *) echo "Usage: $0 <prod|acc> [base-url] [--full]" >&2; exit 2 ;;
esac
BASE_URL="${BASE_URL%/}"
case "$BASE_URL" in http://* | https://*) ;; *) BASE_URL="https://$BASE_URL" ;; esac

KEY="HEALTH_REPORT_TOKEN_$(echo "$ENVIRONMENT" | tr '[:lower:]' '[:upper:]')"
TOKEN="${HEALTH_REPORT_TOKEN:-}"

if [ -z "$TOKEN" ] && [ -f .env ]; then
  # Laatste voorkomen van de key; omringende enkele/dubbele quotes weg.
  TOKEN="$(grep -E "^$KEY=" .env | tail -n1 | cut -d= -f2- | sed -e "s/^'\(.*\)'\$/\1/" -e 's/^"\(.*\)"$/\1/')"
fi

[ -n "$TOKEN" ] || { echo "Geen token: zet $KEY in .env of exporteer HEALTH_REPORT_TOKEN." >&2; exit 2; }

# /health/ready: geen token nodig; allowlist (HEALTH_MONITOR_WHITELIST) en probe_allow_proxied bepalen de toegang.
READY="$(curl -sS --max-time 10 -w '\n%{http_code}' "$BASE_URL/health/ready" 2>&1 || true)"
READY_CODE="${READY##*$'\n'}"
READY_BODY="${READY%$'\n'*}"
echo "ready:  HTTP $READY_CODE  $(printf '%s' "$READY_BODY" | jq -r '.status // empty' 2>/dev/null || true)" >&2

# Header via stdin-config: token staat niet in de processlijst. Geen -f: een 503-report moet leesbaar blijven.
RESPONSE="$(printf 'header = "Authorization: Bearer %s"\n' "$TOKEN" \
  | curl -sS --max-time 10 -K - -w '\n%{http_code}' "$BASE_URL/health/report")"
CODE="${RESPONSE##*$'\n'}"
BODY="${RESPONSE%$'\n'*}"

echo "report: HTTP $CODE  $BASE_URL/health/report" >&2

if ! printf '%s' "$BODY" | jq -e . >/dev/null 2>&1; then
  echo "Geen JSON-antwoord (eerste 300 tekens):" >&2
  printf '%s' "$BODY" | head -c 300 >&2
  echo >&2
  exit 1
fi

printf '%s' "$BODY" | jq "$FILTER"
