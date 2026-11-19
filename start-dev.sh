#!/bin/bash
set -e

# --no-generate: sla scribe:generate en ide-helper over (geen gewijzigde files in git)
SKIP_GENERATE=0
for arg in "$@"; do
  case "$arg" in
    --no-generate) SKIP_GENERATE=1 ;;
  esac
done

echo "🚀 Starting local development (Docker + Vite)…"
# Alle key=value regels uit .env exporteren (comments negeren)
# Let op: gebruik sourcing i.p.v. `export $(... | xargs)`. Bij die laatste doet bash
# nog pathname expansion over het resultaat, waardoor waardes met een kale * (zoals
# cron-expressies) expanderen naar bestandsnamen uit de repo-root.
if [ -f .env ]; then
  set -a
  # shellcheck disable=SC1091
  . ./.env
  set +a
fi

export WWWUSER=${WWWUSER:-1000}
export WWWGROUP=${WWWGROUP:-1000}
export VITE_PORT=${VITE_PORT:-5173}
export VITE_ADMIN_PORT=${VITE_ADMIN_PORT:-5174}
export VITE_HMR_HOST=${VITE_HMR_HOST:-crm.local.privatescan.nl}
export APP_ENV=${APP_ENV:-production}
docker info >/dev/null 2>&1 || { echo "❌ Docker is not running"; exit 1; }

# Lokale TLS-certificaten. Die staan niet in git (zie .gitignore): een cert is
# alleen vertrouwd op de machine waar de bijbehorende CA in de trust store
# staat, dus iedereen maakt zijn eigen set. Zonder dit krijg je in de browser
# "niet vertrouwd" op https://crm.local.privatescan.nl en https://sso.local.privatescan.nl.
if [ ! -f certs/local.pem ] || [ ! -f certs/local-key.pem ]; then
  echo "🔐 Geen lokale certificaten gevonden — aanmaken…"
  ./scripts/generate-local-certs.sh
elif ! security verify-cert -c certs/local.pem >/dev/null 2>&1; then
  CA_PEM="${LOCAL_DEV_CA_DIR:-$HOME/.local-dev-ca}/rootCA.pem"
  echo "⚠️  Het certificaat in certs/ wordt door deze Mac niet vertrouwd."
  if [ -f "$CA_PEM" ]; then
    echo "   Vertrouw je lokale CA eenmalig met:"
    echo "     sudo security add-trusted-cert -d -r trustRoot \\"
    echo "       -k /Library/Keychains/System.keychain \"$CA_PEM\""
  else
    echo "   Er is geen lokale CA. Maak je eigen set met:"
    echo "     ./scripts/generate-local-certs.sh"
  fi
fi

# remove old logs:
VOLUME="privatescan_crm_loki-data"

if docker volume inspect "$VOLUME" >/dev/null 2>&1; then
  echo "Removing volume $VOLUME"
#  docker volume rm "$VOLUME"
else
  echo "Volume $VOLUME does not exist, skipping"
fi

if docker-compose ps crm | grep -q 'Up'; then
  echo "📦 Restarting containers..."
  ./vendor/bin/sail restart
else
  echo "📦 Starting containers..."
  ./vendor/bin/sail up -d
fi

echo "⏳ Waiting for CRM container…"
sleep 5

echo "🧹 Clearing hot files..."
docker-compose exec crm sh -lc "rm -f storage/framework/vite.hot storage/framework/admin-vite.hot || true"

# Alleen Vite-devservers starten als we expliciet in 'dev' modus draaien
#if [ "$APP_ENV" = "dev" ] || [ "$APP_ENV" = "local" ]; then
#    echo "🎨 Starting CRM Vite on port $VITE_PORT..."
#    docker-compose exec -d crm sh -lc "
#        cd /usr/share/nginx/html &&
#        yarn install --silent &&
#        VITE_HMR_HOST=$VITE_HMR_HOST yarn dev --host=0.0.0.0 --port=$VITE_PORT
#    "
#&& npx update-browserslist-db@latest
    echo "🎨 Starting Admin Vite on port $VITE_ADMIN_PORT..."
    docker-compose exec -d crm sh -lc "
        cd /usr/share/nginx/html/packages/Webkul/Admin &&
        npm install --silent &&
        VITE_HMR_HOST=$VITE_HMR_HOST npm run dev -- --host=0.0.0.0 --port=$VITE_ADMIN_PORT
    "
#cd /usr/share/nginx/html/packages/Webkul/Admin && npm install --silent && npm run dev -- --host=0.0.0.0 --port=5174
    echo "⏳ Checking Vite URLs…"
    for i in $(seq 1 30); do
      docker-compose exec crm test -f storage/framework/admin-vite.hot && break
      sleep 1
    done

    echo "🟢 Admin Hotfile:"
    docker-compose exec crm cat storage/framework/admin-vite.hot || echo "⚠️  Hotfile nog niet geschreven (Vite nog aan het opstarten?)"

    echo "🎉 Ready! Visit:"
    echo "   https://crm.local.privatescan.nl"
    echo "   https://$VITE_HMR_HOST:$VITE_ADMIN_PORT (Admin)"

if [ "$SKIP_GENERATE" != 1 ]; then
  # Not exactly the right place, but we don't have ci for this. So generate it here, to minimize human error of forgetting it.
  ./vendor/bin/sail artisan scribe:generate

  ./vendor/bin/sail composer dump-autoload

  # Keep model files clean: only write @mixin, put properties/methods in _ide_helper_models.php
  ./vendor/bin/sail artisan ide-helper:models --write-mixin --reset --no-interaction
  ./vendor/bin/sail artisan ide-helper:generate --no-interaction
else
  ./vendor/bin/sail composer dump-autoload
fi

./vendor/bin/sail artisan boost:mcp
