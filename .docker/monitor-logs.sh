#!/bin/bash

# Script to monitor and report log file permissions and sizes

echo "=== Log File Permission Report ==="
echo "Date: $(date)"
echo

# Check log directory permissions
echo "Log directory permissions:"
ls -la /usr/share/nginx/html/storage/logs/ 2>/dev/null || echo "Log directory not accessible"

echo
echo "Log file details:"
find /usr/share/nginx/html/storage/logs -name "*.log" -exec ls -lah {} \; 2>/dev/null | while read line; do
    echo "$line"
done

echo
echo "Log file sizes:"
du -sh /usr/share/nginx/html/storage/logs/*.log 2>/dev/null || echo "No log files found"

echo
echo "Recent log entries (last 10 lines):"
if [ -f "/usr/share/nginx/html/storage/logs/laravel.log" ]; then
    tail -10 /usr/share/nginx/html/storage/logs/laravel.log
else
    echo "No laravel.log file found"
fi

echo
echo "Permission issues found:"
find /usr/share/nginx/html/storage/logs -name "*.log" -not -user www-data 2>/dev/null | while read file; do
    echo "WARNING: $file is not owned by www-data"
done

echo
echo "=== End Report ==="