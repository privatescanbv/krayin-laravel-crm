#!/bin/bash

set -a
source .env
set +a

# Debug (optioneel)
echo "APP_ENV=$APP_ENV"

# Tag bepalen
if [ "$APP_ENV" = "production" ]; then
  TAG="prod"
else
  TAG="latest"
fi

# This script runs on the DEV and PROD machines to update the Krayin CRM Docker image and run it.
docker pull "ghcr.io/privatescanbv/krayin-laravel-crm/krayincrm:${TAG}" &
docker pull "ghcr.io/privatescanbv/krayin-laravel-crm/keycloak:${TAG}" &
docker pull ghcr.io/privatescanbv/privatecrm/suitecrm:1.0 &
docker pull "ghcr.io/privatescanbv/privateforms/forms:${TAG}" &

wait
echo "Alle pulls zijn klaar!"


docker-compose down

# Maak tijdelijke container van de image om init-bestand te kopiëren
docker create --name temp-crm "ghcr.io/privatescanbv/krayin-laravel-crm/krayincrm:${TAG}"

# Zorg dat doelmap bestaat
mkdir -p ./docker/mysql
rm docker-compose.yml

# Kopieer init-bestand uit container (pas pad aan als nodig)
docker cp temp-crm:/docker/mysql/init-n8n.sql ./docker/mysql/init-n8n.sql
docker cp temp-crm:/docker/mysql/init-forms.sh ./docker/mysql/init-forms.sh
chmod +x ./docker/mysql/init-forms.sh
docker cp temp-crm:/docker/docker-compose.yml ./docker-compose.yml
#docker cp temp-crm:/docker/.env.prod ./.env
docker cp temp-crm:/docker/.env.keycloak.prod ./.env.keycloak
docker cp temp-crm:/docker/config ./docker/config
# Ensure directories exist
mkdir -p ./docker/loki ./docker/promtail
docker cp temp-crm:/docker/loki/loki-config.yml ./docker/loki/local-config.yaml
docker cp temp-crm:/docker/promtail/promtail-config.yml ./docker/promtail/config.yml

# Verwijder tijdelijke container
docker rm temp-crm

sed -i "s|ghcr.io/privatescanbv/\([^:]*\):latest|ghcr.io/privatescanbv/\1:${TAG}|g" ./docker-compose.yml
if [ "$APP_ENV" = "production" ]; then
#  sed -i 's|ghcr.io/privatescanbv/\([^:]*\):latest|ghcr.io/privatescanbv/\1:prod|g' ./docker-compose.yml
  sed -i 's|\.dev\.privatescan\.nl|.privatescan.nl|g' ./docker-compose.yml
else
#  sed -i 's|ghcr.io/privatescanbv/\([^:]*\):latest|ghcr.io/privatescanbv/\1:latest|g' ./docker-compose.yml
  sed -i 's|\.dev\.privatescan\.nl|.privatescan.nl|g' ./docker-compose.yml
fi

#docker-compose down && docker-compose up -d --force-recreate
docker compose up -d
