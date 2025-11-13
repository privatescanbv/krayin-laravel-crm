#!/bin/bash
# This script runs on the DEV and PROD machines to update the Krayin CRM Docker image and run it.
docker pull ghcr.io/privatescanbv/krayin-laravel-crm/krayincrm:latest
docker pull ghcr.io/privatescanbv/krayin-laravel-crm/crmsyncn8n:latest
docker pull ghcr.io/privatescanbv/privatecrm/suitecrm:1.0
docker pull ghcr.io/privatescanbv/privateforms/forms:latest

docker-compose down

# Maak tijdelijke container van de image om init-bestand te kopiëren
docker create --name temp-crm ghcr.io/privatescanbv/krayin-laravel-crm/krayincrm:latest

# Zorg dat doelmap bestaat
mkdir -p ./docker/mysql
rm docker-compose.yml

# Kopieer init-bestand uit container (pas pad aan als nodig)
docker cp temp-crm:/docker/mysql/init-n8n.sql ./docker/mysql/init-n8n.sql
docker cp temp-crm:/docker/mysql/init-forms.sql ./docker/mysql/init-forms.sql
docker cp temp-crm:/docker/docker-compose.yml ./docker-compose.yml
docker cp temp-crm:/docker/.env.prod ./.env
docker cp temp-crm:/docker/config ./docker/config
# Ensure directories exist
mkdir -p ./docker/loki ./docker/promtail
docker cp temp-crm:/docker/loki/loki-config.yml ./docker/loki/loki-config.yml
docker cp temp-crm:/docker/promtail/promtail-config.yml ./docker/promtail/promtail-config.yml

# Verwijder tijdelijke container
docker rm temp-crm

#docker-compose down && docker-compose up -d --force-recreate
docker-compose up -d
