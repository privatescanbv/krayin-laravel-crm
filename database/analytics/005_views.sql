-- =========================================================
-- analytics 005: views voor Metabase
-- Alle bedrijfslogica zit in de dimensies + deze views, niet in Metabase-queries.
--
--   v_verkoopdetail        regel-grain, plat — productanalyse
--   v_speciale_producten   MRI LWS / PTED / Neurochirurg, niet verloren
--   v_orders               order-grain, plat — basis voor de omzetrapporten
--   v_omzet_per_maand(_rapport)  = /admin/reports/revenue-by-month
--   v_omzet_per_medewerker       = /admin/reports/revenue-by-employee
--   v_onderzoeksdagen            = /admin/reports/orders-by-investigation-date
-- =========================================================


-- ---- regel-grain: alles plat (productanalyse) ----
CREATE OR REPLACE VIEW analytics.v_verkoopdetail AS
SELECT
    f.order_item_sk,
    f.order_id,
    f.ordernummer,

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

    du.naam          AS verkoper,

    dp.naam          AS product,
    dp.categorie,
    dp.is_speciaal,
    dp.product_groep,
    dp.hoofd_groep,
    dp.product_type,

    ds.naam             AS pipeline_fase,
    ds.is_gewonnen,
    ds.is_verloren      AS fase_verloren,
    ds.is_order_pipeline,
    ds.afdeling,
    ds.status_categorie,

    f.quantity,
    f.verkoopprijs,
    f.inkoopprijs,
    f.verkoopprijs - COALESCE(f.inkoopprijs, 0) AS marge,
    f.is_verloren    AS regel_verloren

FROM      analytics.fact_order_items   f
JOIN      analytics.dim_date           dd ON dd.date_sk    = f.verkoopdatum_sk
JOIN      analytics.dim_product        dp ON dp.product_sk = f.product_sk
LEFT JOIN analytics.dim_user           du ON du.user_sk    = f.user_sk
LEFT JOIN analytics.dim_pipeline_stage ds ON ds.stage_sk   = f.stage_sk;


CREATE OR REPLACE VIEW analytics.v_speciale_producten AS
SELECT *
FROM analytics.v_verkoopdetail
WHERE is_speciaal    = 1
  AND regel_verloren = 0;


-- ---- order-grain: fact_orders + verkopernaam + datumdimensie ----
CREATE OR REPLACE VIEW analytics.v_orders AS
SELECT
    o.order_sk,
    o.ordernummer,
    o.order_titel,
    o.naam,
    o.verkoper_sk,
    du.naam                    AS verkoper,
    o.stage_sk,
    ds.naam                    AS pipeline_fase,
    o.afdeling,
    o.status_categorie,
    o.is_verloren              AS fase_verloren,
    o.is_gewonnen              AS fase_gewonnen,
    ds.is_order_pipeline,
    o.verkoopdatum_sk          AS verkoopdatum,
    dd.jaar,
    dd.maand_nummer,
    dd.jaar_week,
    dd.week_nummer,
    DATE_FORMAT(o.verkoopdatum_sk, '%Y-%m') AS maand,
    o.gesloten_datum_sk        AS gesloten_datum,
    o.eerste_onderzoek_datum_sk AS eerste_onderzoek_datum,
    o.eerste_onderzoek_at,
    o.verkoopprijs,
    o.inkoopprijs,
    o.marge,
    o.aantal_regels,
    o.aantal_regels_actief
FROM      analytics.fact_orders          o
LEFT JOIN analytics.dim_user             du ON du.user_sk  = o.verkoper_sk
LEFT JOIN analytics.dim_pipeline_stage   ds ON ds.stage_sk = o.stage_sk
LEFT JOIN analytics.dim_date             dd ON dd.date_sk  = o.verkoopdatum_sk;


-- =========================================================
-- Omzet per maand  (= /admin/reports/revenue-by-month)
-- =========================================================
CREATE OR REPLACE VIEW analytics.v_omzet_per_maand AS
SELECT
    maand,
    jaar,
    maand_nummer,
    afdeling,
    status_categorie,
    COUNT(*)          AS aantal_orders,
    SUM(verkoopprijs) AS omzet,
    SUM(inkoopprijs)  AS inkoop,
    SUM(marge)        AS marge
FROM     analytics.v_orders
WHERE    status_categorie IS NOT NULL          -- alleen order-pipeline fasen
GROUP BY maand, jaar, maand_nummer, afdeling, status_categorie;

-- Breed formaat, exact de kolommen van het CRM-rapport.
-- bruto = option + bijna gewonnen + gewonnen + verloren ; netto = bruto - verloren
CREATE OR REPLACE VIEW analytics.v_omzet_per_maand_rapport AS
SELECT
    maand,
    afdeling,
    SUM(CASE WHEN status_categorie = 'option'     THEN omzet ELSE 0 END) AS option_omzet,
    SUM(CASE WHEN status_categorie = 'nearly_won' THEN omzet ELSE 0 END) AS bijna_gewonnen_omzet,
    SUM(CASE WHEN status_categorie = 'won'        THEN omzet ELSE 0 END) AS gewonnen_omzet,
    SUM(CASE WHEN status_categorie = 'lost'       THEN omzet ELSE 0 END) AS verloren_omzet,
    SUM(omzet)                                                           AS bruto_omzet,
    SUM(omzet) - SUM(CASE WHEN status_categorie = 'lost' THEN omzet ELSE 0 END) AS netto_omzet,
    SUM(inkoop)                                                          AS inkoop_totaal
FROM     analytics.v_omzet_per_maand
GROUP BY maand, afdeling;


-- =========================================================
-- Omzet per medewerker  (= /admin/reports/revenue-by-employee)
-- =========================================================
-- Lang formaat: één rij per verkoopdag × verkoper × status_categorie.
-- Metabase pivot: bruto = som alle categorieën, netto = bruto - lost.
CREATE OR REPLACE VIEW analytics.v_omzet_per_medewerker AS
SELECT
    verkoopdatum,
    jaar,
    jaar_week,
    week_nummer,
    maand_nummer,
    maand,
    afdeling,
    verkoper_sk,
    verkoper,
    status_categorie,
    COUNT(*)          AS aantal_orders,
    SUM(verkoopprijs) AS omzet,
    SUM(inkoopprijs)  AS inkoop,
    SUM(marge)        AS marge
FROM     analytics.v_orders
WHERE    status_categorie IS NOT NULL
     AND verkoper_sk IS NOT NULL
GROUP BY verkoopdatum, jaar, jaar_week, week_nummer, maand_nummer, maand,
         afdeling, verkoper_sk, verkoper, status_categorie;


-- =========================================================
-- Verkooporders op onderzoekdatum  (= /admin/reports/orders-by-investigation-date)
-- =========================================================
-- Eén rij per (order × onderzoeksdag), zoals Order::clinicGuideDays():
--   dag 1  = eerste_onderzoek_at (first_examination_at, of vroegste niet-verloren slot)
--   dag 2+ = elke andere kalenderdag met een niet-verloren resource-slot
CREATE OR REPLACE VIEW analytics.v_onderzoeksdagen AS
SELECT
    o.order_sk,
    o.ordernummer,
    o.naam,
    o.eerste_onderzoek_datum       AS onderzoeksdatum,
    o.eerste_onderzoek_at          AS tijdstip,
    o.eerste_onderzoek_at          AS datum_1e_onderzoek,
    o.pipeline_fase                AS wf_status,
    o.afdeling,
    1                              AS is_eerste_dag
FROM   analytics.v_orders o
WHERE  o.eerste_onderzoek_datum IS NOT NULL

UNION

SELECT
    o.order_sk,
    o.ordernummer,
    o.naam,
    p.van_datum_sk                 AS onderzoeksdatum,
    MIN(p.van)                     AS tijdstip,
    o.eerste_onderzoek_at          AS datum_1e_onderzoek,
    o.pipeline_fase                AS wf_status,
    o.afdeling,
    0                              AS is_eerste_dag
FROM   analytics.fact_planning p
JOIN   analytics.v_orders      o ON o.order_sk = p.order_id
WHERE  p.regel_verloren = 0
GROUP BY o.order_sk, o.ordernummer, o.naam, p.van_datum_sk,
         o.eerste_onderzoek_at, o.pipeline_fase, o.afdeling, o.eerste_onderzoek_datum
HAVING o.eerste_onderzoek_datum IS NULL
    OR p.van_datum_sk <> o.eerste_onderzoek_datum;
