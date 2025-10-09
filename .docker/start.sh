#!/bin/bash

echo "Running Laravel setup tasks..."

# Function to fix log permissions robustly
fix_log_permissions() {
    echo "Fixing log permissions..."
    
    # Ensure log directories exist
    mkdir -p /usr/share/nginx/html/storage/logs
    mkdir -p /usr/share/nginx/html/storage/framework/cache
    mkdir -p /usr/share/nginx/html/storage/framework/sessions
    mkdir -p /usr/share/nginx/html/storage/framework/views
    mkdir -p /usr/share/nginx/html/bootstrap/cache
    
    # Fix ownership of all log files to www-data
    find /usr/share/nginx/html/storage/logs -name "*.log" -exec chown www-data:www-data {} \; 2>/dev/null || true
    
    # Set proper permissions on storage directories
    chown -R www-data:www-data /usr/share/nginx/html/storage
    chown -R www-data:www-data /usr/share/nginx/html/bootstrap/cache
    chmod -R 775 /usr/share/nginx/html/storage
    chmod -R 775 /usr/share/nginx/html/bootstrap/cache
    
    # Ensure log files are writable by www-data
    touch /usr/share/nginx/html/storage/logs/laravel.log
    chown www-data:www-data /usr/share/nginx/html/storage/logs/laravel.log
    chmod 664 /usr/share/nginx/html/storage/logs/laravel.log
}

# Fix permissions before any Laravel commands
fix_log_permissions

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
    # Clean old logs but preserve permissions
    find ./storage/logs -name "*.log" -mtime +7 -delete 2>/dev/null || true
fi

# Fix permissions again after Laravel commands
fix_log_permissions

# Set up log rotation to prevent large log files
echo "Setting up log rotation..."
cat > /etc/logrotate.d/laravel << 'EOF'
/usr/share/nginx/html/storage/logs/*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 664 www-data www-data
    postrotate
        # Fix permissions after rotation
        chown -R www-data:www-data /usr/share/nginx/html/storage/logs
        chmod -R 664 /usr/share/nginx/html/storage/logs/*.log
    endscript
}
EOF

# Start logrotate daemon
logrotate -f /etc/logrotate.d/laravel

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
