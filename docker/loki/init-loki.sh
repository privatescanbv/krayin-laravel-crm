#!/bin/sh
# Fix permissions for Loki data directory
set -e

# Create directories if they don't exist
mkdir -p /loki/chunks
mkdir -p /loki/rules

# Fix permissions (Loki runs as user 10001:10001)
chown -R 10001:10001 /loki
chmod -R 755 /loki

echo "Loki permissions fixed"

