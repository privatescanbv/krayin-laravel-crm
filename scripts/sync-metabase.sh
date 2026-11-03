#!/usr/bin/env bash
# Sync alle Metabase-dashboards (incl. gekoppelde vragen) van dev naar acc,
# of met --prod van dev naar prod. Wrapper om `php artisan metabase:sync-dashboard`.
#
# Usage:
#   ./scripts/sync-metabase.sh                 # dev -> acc (met bevestiging)
#   ./scripts/sync-metabase.sh --prod          # dev -> prod (met bevestiging)
#   ./scripts/sync-metabase.sh --dry-run       # alleen tonen wat er zou veranderen
#   ./scripts/sync-metabase.sh --prod --dry-run
#
# Extra opties worden doorgegeven aan het artisan-commando, bijv. --dashboard=12
# of --sync-schema. Bron overschrijven: METABASE_SOURCE=local ./scripts/sync-metabase.sh
# Vereist METABASE_<ENV>_URL en METABASE_<ENV>_API_KEY in .env (zie .env.example).

set -euo pipefail

cd "$(dirname "$0")/.."

SOURCE=${METABASE_SOURCE:-dev}
TARGET=acc
ARGS=()

for arg in "$@"; do
    case "$arg" in
        --prod) TARGET=prod ;;
        -h|--help) sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) ARGS+=("$arg") ;;
    esac
done

if [ "$TARGET" = prod ]; then
    echo "LET OP: dashboards worden overschreven op PRODUCTIE ($SOURCE -> $TARGET)."
fi

if [ -x vendor/bin/sail ] && ! command -v php >/dev/null 2>&1; then
    ARTISAN=(vendor/bin/sail artisan)
elif docker-compose ps --services --filter status=running 2>/dev/null | grep -qx crm; then
    ARTISAN=(docker-compose exec crm php artisan)
else
    ARTISAN=(php artisan)
fi

exec "${ARTISAN[@]}" metabase:sync-dashboard --source="$SOURCE" --target="$TARGET" ${ARGS[@]+"${ARGS[@]}"}
