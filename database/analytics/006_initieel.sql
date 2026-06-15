-- =========================================================
-- analytics 006: initieel laden
-- Eenmalig uitvoeren na scripts 001 t/m 005.
-- De watermark staat op 2020-01-01, waardoor sync_all()
-- alle historische data laadt.
-- =========================================================

CALL analytics.sync_all();

-- Verificatie: verwachte aantallen
SELECT 'dim_date'          AS tabel, COUNT(*) AS rijen FROM analytics.dim_date
UNION ALL
SELECT 'dim_product',                COUNT(*) FROM analytics.dim_product
UNION ALL
SELECT 'dim_user',                   COUNT(*) FROM analytics.dim_user
UNION ALL
SELECT 'dim_pipeline_stage',         COUNT(*) FROM analytics.dim_pipeline_stage
UNION ALL
SELECT 'fact_order_items',           COUNT(*) FROM analytics.fact_order_items;

-- Controle speciale producten
SELECT categorie, COUNT(*) AS verkopen, SUM(verkoopprijs) AS omzet
FROM analytics.v_speciale_producten
GROUP BY categorie
ORDER BY categorie;
