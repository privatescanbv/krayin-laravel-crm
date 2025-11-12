#!/bin/bash
# Starts the local docker compose and starts the vite server for local development.
# Note: alle css and js files should be loaded from localhost:5137, check the html if this works (hot deployment, vite)

# Start Docker Compose met Vite dev server
echo "🚀 Starting Docker Compose with Vite dev server..."

# Set defaults to avoid docker-compose warnings
export WWWUSER=${WWWUSER:-1000}
export WWWGROUP=${WWWGROUP:-1000}

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# Start alle services
echo "📦 Starting Docker containers..."
./vendor/bin/sail down && ./vendor/bin/sail up -d

# Wacht tot de containers draaien
echo "⏳ Waiting for containers to start..."
sleep 5

# Check if CRM container is running
if ! docker-compose ps crm | grep -q "Up"; then
    echo "❌ CRM container failed to start. Check logs: docker-compose logs crm"
    exit 1
fi

# Start Vite dev servers in de CRM container
echo "🎨 Starting Vite dev server..."

# Root app Vite (port 5173)
docker-compose exec -d crm sh -lc "yarn install && yarn dev -- --host 0.0.0.0 --port 5173"

# Webkul/Admin Vite (port 5174)
docker-compose exec -d crm sh -lc "cd packages/Webkul/Admin && npm install && npm run dev -- --host 0.0.0.0 --port 5174"
# Wait a bit for Vite to start
sleep 3

echo "✅ Development environment ready!"
echo "🌐 Laravel: http://localhost:8000"
echo "🎨 Vite: http://localhost:5173"
echo ""
echo "📝 To view logs: docker-compose logs -f crm"
echo "🛑 To stop: docker-compose down"
echo ""
echo "🔧 To restart Vite: docker-compose exec crm npm run dev"

# Robust HTTP checks for Vite servers (poll until ready)
wait_for_http() {
    url="$1"
    allowed_codes="$2" # space-separated list, e.g. "200 404"
    retries=${3:-60}
    delay=${4:-1}

    echo "⏳ Waiting for $url (allowed: $allowed_codes)"
    for i in $(seq 1 "$retries"); do
        code=$(curl -s -o /dev/null -w "%{http_code}" "$url" || echo "000")
        for ac in $allowed_codes; do
            if [ "$code" = "$ac" ]; then
                echo "✅ $url responded with $code"
                return 0
            fi
        done
        sleep "$delay"
    done
    echo "❌ $url did not respond with one of [$allowed_codes] in time (last: $code)"
    exit 1
}

# Vite dev server typically serves /@vite/client with 200 when ready
wait_for_http "http://localhost:5173/@vite/client" "200"
wait_for_http "http://localhost:5174/@vite/client" "200"
