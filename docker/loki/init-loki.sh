#!/bin/sh
# Fix permissions for Loki data directory
set -e

# Check if permissions need fixing
if [ ! -d /loki/chunks ] || [ ! -w /loki/chunks ]; then
    echo "Fixing Loki permissions..."
    
    # Create directories if they don't exist
    mkdir -p /loki/chunks
    mkdir -p /loki/rules
    
    # Fix permissions (Loki runs as user 10001:10001)
    chown -R 10001:10001 /loki
    chmod -R 755 /loki
    
    echo "Loki permissions fixed"
else
    echo "Loki permissions already correct"
fi

