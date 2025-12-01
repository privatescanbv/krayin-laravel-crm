#!/bin/bash
set -e

echo "🚀 Starting local development (Docker + Vite)…"
# Alle key=value regels uit .env exporteren (comments negeren)
if [ -f .env ]; then
  export $(grep -v '^#' .env | grep '=' | xargs)
fi

export WWWUSER=${WWWUSER:-1000}
export WWWGROUP=${WWWGROUP:-1000}
export VITE_PORT=${VITE_PORT:-5173}
export VITE_ADMIN_PORT=${VITE_ADMIN_PORT:-5174}
export VITE_HMR_HOST=${VITE_HMR_HOST:-crm.local.privatescan.nl}
export APP_ENV=${APP_ENV:-production}
docker info >/dev/null 2>&1 || { echo "❌ Docker is not running"; exit 1; }

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
if [ "$APP_ENV" = "dev" ] || [ "$APP_ENV" = "local" ]; then
    echo "🎨 Starting CRM Vite on port $VITE_PORT..."
    docker-compose exec -d crm sh -lc "
        cd /usr/share/nginx/html &&
        yarn install --silent &&
        VITE_HMR_HOST=$VITE_HMR_HOST yarn dev --host=0.0.0.0 --port=$VITE_PORT
    "

    echo "🎨 Starting Admin Vite on port $VITE_ADMIN_PORT..."
    docker-compose exec -d crm sh -lc "
        cd /usr/share/nginx/html/packages/Webkul/Admin &&
        npm install --silent &&
        VITE_HMR_HOST=$VITE_HMR_HOST npm run dev -- --host=0.0.0.0 --port=$VITE_ADMIN_PORT
    "

    echo "⏳ Checking Vite URLs…"
    sleep 5

    echo "🟢 CRM Hotfile:"
    docker-compose exec crm cat storage/framework/vite.hot

    echo "🟢 Admin Hotfile:"
    docker-compose exec crm cat storage/framework/admin-vite.hot

    echo "🎉 Ready! Visit:"
    echo "   https://crm.local.privatescan.nl"
    echo "   https://$VITE_HMR_HOST:$VITE_PORT  (CRM)"
    echo "   https://$VITE_HMR_HOST:$VITE_ADMIN_PORT (Admin)"
else
    echo "🎉 Containers opnieuw gestart (geen lokale Vite-devservers gestart)."
fi

