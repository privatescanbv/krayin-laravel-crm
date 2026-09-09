-- =========================================================
-- analytics 006: (her)opbouw + verificatie
--
-- Volgorde bij een (her)opbouw:
--   001_schema.sql          -- tabellen (DROP + CREATE)
--   002_dim_date.sql        -- dim_date vullen (statisch, 2020-2035)
--   003_sync_procedure.sql  -- sync_all()
--   004_event.sql           -- uurlijks event
--   005_views.sql           -- views
--   006_initieel.sql        -- dit script: eerste sync + checks
--
-- sync_all() doet full reload, dus opnieuw draaien is altijd veilig.
-- =========================================================

CALL analytics.sync_all();

-- ---- Rij-aantallen ----
SELECT 'dim_date'           AS tabel, COUNT(*) AS rijen FROM analytics.dim_date
UNION ALL SELECT 'dim_product',          COUNT(*) FROM analytics.dim_product
UNION ALL SELECT 'dim_user',             COUNT(*) FROM analytics.dim_user
UNION ALL SELECT 'dim_pipeline_stage',   COUNT(*) FROM analytics.dim_pipeline_stage
UNION ALL SELECT 'fact_orders',          COUNT(*) FROM analytics.fact_orders
UNION ALL SELECT 'fact_order_items',     COUNT(*) FROM analytics.fact_order_items
UNION ALL SELECT 'fact_planning',        COUNT(*) FROM analytics.fact_planning;

-- ---- fact_order_items mag geen wees-rijen bevatten ----
SELECT COUNT(*) AS wees_orderregels
FROM analytics.fact_order_items f
LEFT JOIN privatescan.order_items oi ON oi.id = f.order_item_sk
WHERE oi.id IS NULL;

-- ---- Controle "Omzet per maand" (vergelijk met /admin/reports/revenue-by-month) ----
SELECT maand, afdeling, gewonnen_omzet, bijna_gewonnen_omzet, option_omzet,
       verloren_omzet, netto_omzet, bruto_omzet, inkoop_totaal
FROM analytics.v_omzet_per_maand_rapport
ORDER BY maand DESC, afdeling
LIMIT 24;

-- ---- Controle "Omzet per medewerker" (laatste weken) ----
SELECT jaar_week, verkoper,
       SUM(omzet)                                                  AS bruto,
       SUM(omzet) - SUM(CASE WHEN status_categorie='lost' THEN omzet ELSE 0 END) AS netto,
       SUM(inkoop)                                                 AS inkoop
FROM analytics.v_omzet_per_medewerker
GROUP BY jaar_week, verkoper
ORDER BY jaar_week DESC, bruto DESC
LIMIT 20;

-- ---- Controle "Verkooporders op onderzoekdatum" (deze + volgende week) ----
SELECT onderzoeksdatum, ordernummer, naam, tijdstip, wf_status, is_eerste_dag
FROM analytics.v_onderzoeksdagen
WHERE onderzoeksdatum BETWEEN CURDATE() - INTERVAL 7 DAY AND CURDATE() + INTERVAL 14 DAY
ORDER BY tijdstip;

-- ---- Speciale producten ----
SELECT categorie, COUNT(*) AS verkopen, SUM(verkoopprijs) AS omzet
FROM analytics.v_speciale_producten
GROUP BY categorie
ORDER BY categorie;
