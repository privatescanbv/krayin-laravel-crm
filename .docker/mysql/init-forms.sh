#!/bin/bash
# Init Forms Database Script
# Reads FORMS_DB_PASSWORD from the container environment (set via docker-compose).
set -e

: "${FORMS_DB_PASSWORD:?FORMS_DB_PASSWORD env var is required}"
: "${DB_ANALYTICS_USERNAME:?DB_ANALYTICS_USERNAME env var is required}"
: "${DB_ANALYTICS_PASSWORD:?DB_ANALYTICS_PASSWORD env var is required}"

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`forms\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS 'formadmin'@'%' IDENTIFIED BY '${FORMS_DB_PASSWORD}';
    ALTER USER 'formadmin'@'%' IDENTIFIED BY '${FORMS_DB_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`forms\`.*  TO 'formadmin'@'%';

    CREATE DATABASE IF NOT EXISTS \`analytics\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS '${DB_ANALYTICS_USERNAME}'@'%' IDENTIFIED BY '${DB_ANALYTICS_PASSWORD}';
    ALTER USER '${DB_ANALYTICS_USERNAME}'@'%' IDENTIFIED BY '${DB_ANALYTICS_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`analytics\`.* TO '${DB_ANALYTICS_USERNAME}'@'%';
    GRANT SELECT ON \`privatescan\`.* TO '${DB_ANALYTICS_USERNAME}'@'%';

    FLUSH PRIVILEGES;
EOSQL

# Clinic portal database (privatescanbv/clinic-portal); optional so existing setups without it keep working.
if [ -n "${CLINIC_DB_PASSWORD:-}" ]; then
mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`clinic\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS 'clinicadmin'@'%' IDENTIFIED BY '${CLINIC_DB_PASSWORD}';
    ALTER USER 'clinicadmin'@'%' IDENTIFIED BY '${CLINIC_DB_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`clinic\`.* TO 'clinicadmin'@'%';
    FLUSH PRIVILEGES;
EOSQL
fi
