#!/bin/bash

# Health check script to verify log permissions and application health

echo "Performing health check..."

# Check if log directory exists and is writable
if [ ! -d "/usr/share/nginx/html/storage/logs" ]; then
    echo "ERROR: Log directory does not exist"
    exit 1
fi

# Check if we can write to the log directory
if ! touch /usr/share/nginx/html/storage/logs/health-check.log 2>/dev/null; then
    echo "ERROR: Cannot write to log directory"
    exit 1
fi

# Check log file ownership
LOG_FILES=$(find /usr/share/nginx/html/storage/logs -name "*.log" 2>/dev/null)
if [ -n "$LOG_FILES" ]; then
    for log_file in $LOG_FILES; do
        OWNER=$(stat -c '%U' "$log_file" 2>/dev/null)
        if [ "$OWNER" != "www-data" ]; then
            echo "WARNING: Log file $log_file is owned by $OWNER, should be www-data"
            # Try to fix it
            chown www-data:www-data "$log_file" 2>/dev/null || true
        fi
    done
fi

# Check if Laravel application is responding
if ! php /usr/share/nginx/html/artisan --version >/dev/null 2>&1; then
    echo "ERROR: Laravel application is not responding"
    exit 1
fi

# Check if we can write to Laravel log
if ! php -r "file_put_contents('/usr/share/nginx/html/storage/logs/health-check.log', 'Health check: ' . date('Y-m-d H:i:s') . PHP_EOL);" 2>/dev/null; then
    echo "ERROR: Cannot write to Laravel log file"
    exit 1
fi

# Clean up health check log
rm -f /usr/share/nginx/html/storage/logs/health-check.log

echo "Health check passed"
exit 0