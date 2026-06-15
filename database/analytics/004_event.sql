-- =========================================================
-- analytics 004: MySQL Event — uurlijkse automatische sync
--
-- Vereisten:
--   1. event_scheduler = ON in MySQL config (persistent na herstart):
--      Voeg toe aan /etc/mysql/mysql.conf.d/mysqld.cnf:
--        [mysqld]
--        event_scheduler = ON
--
--   2. Of tijdelijk (tot next restart):
--      SET GLOBAL event_scheduler = ON;
--
--   3. De MySQL user heeft EVENT privilege nodig:
--      GRANT EVENT ON analytics.* TO 'jouw_user'@'%';
-- =========================================================

SET GLOBAL event_scheduler = ON;

DROP EVENT IF EXISTS analytics.evt_sync_analytics;

CREATE EVENT analytics.evt_sync_analytics
    ON SCHEDULE EVERY 1 HOUR
    STARTS CURRENT_TIMESTAMP
    COMMENT 'Synchroniseert CRM-data (privatescan) naar analytics schema'
    DO CALL analytics.sync_all();
