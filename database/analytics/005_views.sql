-- =========================================================
-- analytics 005: views voor Metabase
-- Alle bedrijfslogica zit in de dimensietabellen en hier,
-- niet in Metabase-queries.
-- =========================================================

-- ---- Hoofdview: alles plat — basis voor alle Metabase-dashboards ----
CREATE OR REPLACE VIEW analytics.v_verkoopdetail AS
SELECT
    f.order_item_sk,
    f.order_id,
    f.ordernummer,

    -- Datumdimensie
    dd.date_sk       AS verkoopdatum,
    dd.dag_naam,
    dd.dag_nummer,
    dd.week_nummer,
    dd.jaar_week,
    dd.maand_naam,
    dd.maand_nummer,
    dd.kwartaal,
    dd.jaar,
    dd.is_werkdag,

    -- Verkoper
    du.naam          AS verkoper,

    -- Product
    dp.naam          AS product,
    dp.categorie,
    dp.is_speciaal,
    dp.product_groep,
    dp.hoofd_groep,
    dp.product_type,

    -- Pipelinefase
    ds.naam             AS pipeline_fase,
    ds.is_gewonnen,
    ds.is_verloren      AS fase_verloren,
    ds.is_order_pipeline,

    -- Maten
    f.quantity,
    f.verkoopprijs,
    f.inkoopprijs,
    f.verkoopprijs - COALESCE(f.inkoopprijs, 0) AS marge,
    f.is_verloren    AS regel_verloren

FROM      analytics.fact_order_items   f
JOIN      analytics.dim_date           dd ON dd.date_sk   = f.verkoopdatum_sk
JOIN      analytics.dim_product        dp ON dp.product_sk = f.product_sk
LEFT JOIN analytics.dim_user           du ON du.user_sk   = f.user_sk
LEFT JOIN analytics.dim_pipeline_stage ds ON ds.stage_sk  = f.stage_sk;


-- ---- Speciale producten: MRI LWS / PTED / Neurochirurg, niet verloren ----
CREATE OR REPLACE VIEW analytics.v_speciale_producten AS
SELECT *
FROM analytics.v_verkoopdetail
WHERE is_speciaal    = 1
  AND regel_verloren = 0;


-- ---- Omzet per medewerker per dag (equivalent van CRM-rapport) ----
-- Gefilterd op Order-pipeline (PS + Hernia), niet-verloren fasen,
-- niet-verloren orderregels — zelfde scope als RevenueByEmployeeController.
CREATE OR REPLACE VIEW analytics.v_omzet_per_medewerker AS
SELECT
    verkoopdatum,
    jaar_week,
    verkoper,
    COUNT(DISTINCT order_id) AS aantal_orders,
    COUNT(*)                 AS aantal_regels,
    SUM(verkoopprijs)        AS omzet,
    SUM(inkoopprijs)         AS inkoopkosten,
    SUM(marge)               AS marge
FROM analytics.v_verkoopdetail
WHERE is_order_pipeline = 1
  AND fase_verloren     = 0
  AND regel_verloren    = 0
GROUP BY verkoopdatum, jaar_week, verkoper
ORDER BY verkoopdatum DESC, verkoper;
