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

# storage/framework and bootstrap/cache aren't volume-mounted, so a fresh
# container after a deploy only has what the image's build-time mkdir gave it
# (which doesn't include storage/framework/cache/data). Recreate them here so
# php-fpm/queue/scheduler, which all start in parallel right after this
# script, never hit a missing cache directory (file_put_contents ENOENT).
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage/framework storage/logs bootstrap/cache
chmod -R 775 storage/framework storage/logs bootstrap/cache

php artisan config:clear
php artisan optimize:clear
php artisan config:cache
php artisan storage:link || true
# migrate database by supervisord

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
