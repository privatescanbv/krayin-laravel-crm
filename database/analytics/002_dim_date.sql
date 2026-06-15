-- =========================================================
-- analytics 002: dim_date vullen 2020-01-01 t/m 2035-12-31
-- Idempotent via INSERT IGNORE
-- =========================================================

-- 2020-2035 = ~5844 dagen; standaard limiet is 1000
SET SESSION cte_max_recursion_depth = 6000;

INSERT IGNORE INTO analytics.dim_date
    (date_sk, dag_naam, dag_nummer, week_nummer, jaar_week,
     maand_nummer, maand_naam, kwartaal, jaar, is_werkdag)
WITH RECURSIVE gen AS (
    SELECT DATE('2020-01-01') AS d
    UNION ALL
    SELECT DATE_ADD(d, INTERVAL 1 DAY) FROM gen WHERE d < '2035-12-31'
)
SELECT
    d,
    CASE DAYOFWEEK(d)
        WHEN 2 THEN 'Maandag'   WHEN 3 THEN 'Dinsdag'
        WHEN 4 THEN 'Woensdag'  WHEN 5 THEN 'Donderdag'
        WHEN 6 THEN 'Vrijdag'   WHEN 7 THEN 'Zaterdag'
        WHEN 1 THEN 'Zondag'
    END                                                             AS dag_naam,
    CASE DAYOFWEEK(d) WHEN 1 THEN 7 ELSE DAYOFWEEK(d) - 1 END     AS dag_nummer,
    WEEK(d, 3)                                                      AS week_nummer,
    CONCAT(YEAR(d), '-W', LPAD(WEEK(d, 3), 2, '0'))                AS jaar_week,
    MONTH(d)                                                        AS maand_nummer,
    CASE MONTH(d)
        WHEN 1  THEN 'Januari'    WHEN 2  THEN 'Februari'
        WHEN 3  THEN 'Maart'      WHEN 4  THEN 'April'
        WHEN 5  THEN 'Mei'        WHEN 6  THEN 'Juni'
        WHEN 7  THEN 'Juli'       WHEN 8  THEN 'Augustus'
        WHEN 9  THEN 'September'  WHEN 10 THEN 'Oktober'
        WHEN 11 THEN 'November'   WHEN 12 THEN 'December'
    END                                                             AS maand_naam,
    QUARTER(d)                                                      AS kwartaal,
    YEAR(d)                                                         AS jaar,
    DAYOFWEEK(d) BETWEEN 2 AND 6                                    AS is_werkdag
FROM gen;
