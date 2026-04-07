#!/bin/bash
# Init Forms Database Script
# Reads FORMS_DB_PASSWORD from the container environment (set via docker-compose).
set -e

: "${FORMS_DB_PASSWORD:?FORMS_DB_PASSWORD env var is required}"

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`forms\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS 'formadmin'@'%' IDENTIFIED BY '${FORMS_DB_PASSWORD}';
    ALTER USER 'formadmin'@'%' IDENTIFIED BY '${FORMS_DB_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`forms\`.*  TO 'formadmin'@'%';
    FLUSH PRIVILEGES;
EOSQL
