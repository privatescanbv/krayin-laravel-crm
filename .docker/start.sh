#!/bin/bash

echo "Running Laravel setup tasks..."

php artisan config:clear
php artisan optimize:clear
php artisan config:cache
php artisan migrate --force || true
php artisan migrate --path=vendor/laravel/sanctum/database/migrations --force || true
php artisan storage:link || true

# Ensure log-viewer assets are available (idempotent)
if [ ! -d "public/vendor/log-viewer" ]; then
    echo "Publishing log-viewer assets..."
    php artisan vendor:publish --tag=log-viewer-assets --force || true
    # remove old logs, by default.
    rm ./storage/logs/*.log
fi

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
