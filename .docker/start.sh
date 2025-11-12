#!/bin/bash

echo "Running Laravel setup tasks..."

php artisan config:clear
php artisan optimize:clear
php artisan config:cache
php artisan storage:link || true
# migrate database by supervisord


# Fix storage permissions to prevent log write errors
echo "Fixing storage permissions..."
chown -R www-data:www-data /usr/share/nginx/html/storage
chown -R www-data:www-data /usr/share/nginx/html/bootstrap/cache
chmod -R 775 /usr/share/nginx/html/storage
chmod -R 775 /usr/share/nginx/html/bootstrap/cache

# Ensure log directories exist and have correct permissions
mkdir -p /usr/share/nginx/html/storage/logs
mkdir -p /usr/share/nginx/html/storage/framework/cache
mkdir -p /usr/share/nginx/html/storage/framework/sessions
mkdir -p /usr/share/nginx/html/storage/framework/views
chown -R www-data:www-data /usr/share/nginx/html/storage
chmod -R 775 /usr/share/nginx/html/storage

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
