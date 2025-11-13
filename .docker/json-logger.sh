#!/bin/bash
# JSON Logger - Converts non-JSON logs to JSON format compatible with Laravel JSON logs
# If input is already JSON, pass it through unchanged
# Usage: command | json-logger.sh [program_name] [level]

PROGRAM_NAME="${1:-unknown}"
LOG_LEVEL="${2:-INFO}"

# Map log level to numeric value (same as Laravel/Monolog)
case "$LOG_LEVEL" in
    DEBUG) LEVEL_NUM=100 ;;
    INFO) LEVEL_NUM=200 ;;
    NOTICE) LEVEL_NUM=250 ;;
    WARNING|WARN) LEVEL_NUM=300 ;;
    ERROR) LEVEL_NUM=400 ;;
    CRITICAL) LEVEL_NUM=500 ;;
    ALERT) LEVEL_NUM=550 ;;
    EMERGENCY) LEVEL_NUM=600 ;;
    *) LEVEL_NUM=200 ;;
esac

# Read from stdin line by line
while IFS= read -r line || [ -n "$line" ]; do
    # Skip empty lines
    [ -z "$line" ] && continue
    
    # Check if the line is already valid JSON (starts with { and ends with })
    # Simple check: starts with { and ends with }, and contains "datetime" or "message" field (Laravel JSON format)
    if echo "$line" | grep -qE '^\s*\{.*\}\s*$' && (echo "$line" | grep -qE '"datetime"|"message"|"level_name"'); then
        # Likely already JSON, pass through unchanged
        echo "$line"
        continue
    fi
    
    # Not JSON, convert to JSON format
    timestamp=$(date -u +"%Y-%m-%dT%H:%M:%S.%3NZ")
    
    # Escape JSON special characters in the message
    escaped_line=$(printf '%s' "$line" | sed 's/\\/\\\\/g' | sed 's/"/\\"/g' | sed 's/\n/\\n/g' | sed 's/\r/\\r/g' | sed 's/\t/\\t/g')
    
    # Output as JSON (same format as Laravel JSON logs for consistency)
    echo "{\"datetime\":\"$timestamp\",\"level\":$LEVEL_NUM,\"level_name\":\"$LOG_LEVEL\",\"channel\":\"$PROGRAM_NAME\",\"message\":\"$escaped_line\"}"
done
