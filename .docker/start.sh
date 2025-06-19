#!/bin/bash

echo "Running Laravel setup tasks..."

php artisan config:clear
php artisan optimize:clear
php artisan config:cache
php artisan migrate --force || true
php artisan migrate --path=vendor/laravel/sanctum/database/migrations --force || true
php artisan storage:link || true

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
