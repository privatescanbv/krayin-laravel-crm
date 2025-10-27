#!/bin/bash

# Start Docker Compose met Vite dev server
echo "🚀 Starting Docker Compose with Vite dev server..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# Start alle services
echo "📦 Starting Docker containers..."
docker-compose up -d

# Wacht tot de containers draaien
echo "⏳ Waiting for containers to start..."
sleep 15

# Check if CRM container is running
if ! docker-compose ps crm | grep -q "Up"; then
    echo "❌ CRM container failed to start. Check logs: docker-compose logs crm"
    exit 1
fi

# Start Vite dev server in de CRM container
echo "🎨 Starting Vite dev server..."
docker-compose exec -d crm npm run dev -- --host 0.0.0.0 --port 5173
# Wait a bit for Vite to start
sleep 5

echo "✅ Development environment ready!"
echo "🌐 Laravel: http://localhost:8000"
echo "🎨 Vite: http://localhost:5173"
echo ""
echo "📝 To view logs: docker-compose logs -f crm"
echo "🛑 To stop: docker-compose down"
echo ""
echo "🔧 To restart Vite: docker-compose exec crm npm run dev"
