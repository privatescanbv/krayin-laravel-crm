#!/bin/bash
# Init Sugarcrm Database Script
# Reads SUGARCRM_DB_DATABASE etc from the container environment (set via docker-compose).
set -e

: "${SUGARCRM_DB_DATABASE:?env var required}"
: "${SUGARCRM_DB_USERNAME:?env var required}"
: "${SUGARCRM_DB_PASSWORD:?env var required}"

echo "DB: $SUGARCRM_DB_DATABASE"
echo "USER: $SUGARCRM_DB_USERNAME"

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`${SUGARCRM_DB_DATABASE}\`
      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS '${SUGARCRM_DB_USERNAME}'@'%'
      IDENTIFIED BY '${SUGARCRM_DB_PASSWORD}';
    ALTER USER '${SUGARCRM_DB_USERNAME}'@'%'
      IDENTIFIED BY '${SUGARCRM_DB_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`${SUGARCRM_DB_DATABASE}\`.*
      TO '${SUGARCRM_DB_USERNAME}'@'%';
    FLUSH PRIVILEGES;
EOSQL
