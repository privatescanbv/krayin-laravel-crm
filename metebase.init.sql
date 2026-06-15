CREATE DATABASE metabase;

CREATE USER 'metabase'@'%' IDENTIFIED BY 'c8f42348e69';
GRANT ALL PRIVILEGES ON metabase.* TO 'metabase'@'%';

FLUSH PRIVILEGES;



CREATE USER 'metabase_ro'@'%' IDENTIFIED BY 'c8f42348e69';

GRANT SELECT ON privatescan_crm.* TO 'metabase_ro'@'%';

FLUSH PRIVILEGES;



ALTER USER 'metabase'@'%'
    IDENTIFIED WITH mysql_native_password
        BY 'c8f42348e69';

ALTER USER 'metabase_ro'@'%'
    IDENTIFIED WITH mysql_native_password
        BY 'c8f42348e69';

FLUSH PRIVILEGES;
