#!/bin/bash
set -e

echo "🚀 Starting local development (Docker + Vite)…"

export WWWUSER=${WWWUSER:-1000}
export WWWGROUP=${WWWGROUP:-1000}
export VITE_PORT=${VITE_PORT:-5173}
export VITE_ADMIN_PORT=${VITE_ADMIN_PORT:-5174}
export VITE_HMR_HOST=${VITE_HMR_HOST:-crm.local.privatescan.nl}

docker info >/dev/null 2>&1 || { echo "❌ Docker is not running"; exit 1; }

echo "📦 Restarting containers..."
./vendor/bin/sail restart

echo "⏳ Waiting for CRM container…"
sleep 5

echo "🧹 Clearing hot files..."
docker-compose exec crm sh -lc "rm -f storage/framework/vite.hot storage/framework/admin-vite.hot || true"

# Alleen Vite-devservers starten als we expliciet in 'dev' modus draaien
if [ "$VITE_HMR_HOST" = "dev" ]; then
    echo "🎨 Starting CRM Vite..."
    docker-compose exec -d crm sh -lc "
        cd /usr/share/nginx/html &&
        yarn install --silent &&
        VITE_HMR_HOST=$VITE_HMR_HOST yarn dev --host=0.0.0.0 --port=$VITE_PORT
    "

    echo "🎨 Starting Admin Vite..."
    docker-compose exec -d crm sh -lc "
        cd /usr/share/nginx/html/packages/Webkul/Admin &&
        npm install --silent &&
        VITE_HMR_HOST=$VITE_HMR_HOST npm run dev -- --host=0.0.0.0 --port=$VITE_ADMIN_PORT
    "

    echo "⏳ Checking Vite URLs…"
    sleep 3

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

