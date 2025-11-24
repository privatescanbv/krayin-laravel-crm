#!/bin/bash
# Script om Loki volume permissions te fixen
# Gebruik: ./fix-loki-permissions.sh

set -e

echo "Stoppen van Loki container..."
docker-compose stop loki 2>/dev/null || true

echo "Verwijderen van loki-data volume..."
docker volume rm krayin-laravel-crm_loki-data 2>/dev/null || true

echo "Starten van loki-init container om permissions te fixen..."
docker-compose up -d loki-init

echo "Wachten tot init container klaar is..."
docker-compose wait loki-init

echo "Starten van Loki..."
docker-compose up -d loki

echo "Loki permissions zijn gefixt!"
echo "Je kunt de logs controleren met: docker-compose logs loki"

