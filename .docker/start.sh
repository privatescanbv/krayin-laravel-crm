#!/bin/bash

echo "Running Laravel setup tasks..."

# Ensure production env is present before running artisan commands
if [ -f /docker/.env.prod ]; then
  echo "Applying /docker/.env.prod to application .env"
  cp /docker/.env.prod /usr/share/nginx/html/.env
  chown www-data:www-data /usr/share/nginx/html/.env || true
else
  echo "Warning: /docker/.env.prod not found; proceeding with existing env"
fi

php artisan config:clear
php artisan optimize:clear
php artisan config:cache
php artisan migrate --force || true
php artisan migrate --path=vendor/laravel/sanctum/database/migrations --force || true
php artisan storage:link || true

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
