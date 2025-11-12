#!/bin/bash

echo "Running Laravel setup tasks..."

# Adjust sail user UID/GID if WWWUSER/WWWGROUP are set (Laravel Sail compatibility)
# Only adjust if WWWUSER/WWWGROUP are numeric (not "www-data" or empty)
if [ ! -z "$WWWUSER" ] && [ "$WWWUSER" -eq "$WWWUSER" ] 2>/dev/null; then
    usermod -u $WWWUSER sail 2>/dev/null || true
fi
if [ ! -z "$WWWGROUP" ] && [ "$WWWGROUP" -eq "$WWWGROUP" ] 2>/dev/null; then
    groupmod -g $WWWGROUP sail 2>/dev/null || true
    usermod -g $WWWGROUP sail 2>/dev/null || true
fi
# Ensure sail user is in sail group (fix if it's in www-data group)
usermod -g sail sail 2>/dev/null || true

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
mkdir -p /var/log/nginx /var/log/php-fpm /var/log/supervisor
chown -R www-data:www-data /usr/share/nginx/html/storage
chown -R www-data:www-data /var/log/nginx
chown -R www-data:www-data /var/log/php-fpm
chown -R www-data:www-data /var/log/supervisor
chmod -R 775 /usr/share/nginx/html/storage
chmod -R 755 /var/log/nginx
chmod -R 755 /var/log/php-fpm
chmod -R 755 /var/log/supervisor

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
