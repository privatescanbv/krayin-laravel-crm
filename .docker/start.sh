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

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
