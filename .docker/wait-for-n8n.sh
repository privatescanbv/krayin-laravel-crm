#!/bin/bash

#!/bin/bash
#set -e
#export PYTHONUNBUFFERED=1
#export NODE_ENV=production
#exec 1>&2   # Schrijf stdout naar stderr zodat het zichtbaar blijft

echo "⏳ Wachten op MySQL (mysql:3306)..."
until nc -z mysql 3306; do
  sleep 1
done
echo "✅ MySQL is bereikbaar."

echo "⏳ Wachten tot n8n API beschikbaar is..."
until curl -s --fail http://n8n:5678/healthz > /dev/null; do
  sleep 2
done
echo "✅ n8n is klaar"
exec "$@"
