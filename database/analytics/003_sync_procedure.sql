-- =========================================================
-- analytics 003: sync_all() — alle dimensies én feiten volledig herladen
--
-- Full reload i.p.v. incrementeel: de bronnen zijn klein (orders ~1.5k,
-- orderregels ~5k, planning-slots ~4k) en full reload verwijdert elke
-- drift-bron (verwijderde rijen, gemiste FK-wijzigingen, watermark-corruptie).
-- ponytail: full reload; bij >1M orderregels incrementeel heroverwegen.
--
-- Idempotent via DROP/CREATE.
-- =========================================================

DROP PROCEDURE IF EXISTS analytics.sync_all;

DELIMITER $$

CREATE DEFINER='privatescan-analytics'@'%' PROCEDURE analytics.sync_all()
BEGIN
    -- ========================================================
    -- DIMENSIES
    -- ========================================================

    -- dim_product
    -- product_groups: pg1 = bladgroep, pg2 = middengroep, pg3 = hoofdgroep
    REPLACE INTO analytics.dim_product
        (product_sk, naam, external_id, product_type, product_groep, hoofd_groep, actief, geladen_op)
    SELECT
        p.id,
        COALESCE(p.name, 'Onbekend'),
        p.external_id,
        pt.name                                               AS product_type,
        pg1.name                                              AS product_groep,
        COALESCE(pg3.name, pg2.name, pg1.name)               AS hoofd_groep,
        p.active,
        NOW()
    FROM privatescan.products p
    LEFT JOIN privatescan.product_groups pg1 ON pg1.id = p.product_group_id
    LEFT JOIN privatescan.product_groups pg2 ON pg2.id = pg1.parent_id
    LEFT JOIN privatescan.product_groups pg3 ON pg3.id = pg2.parent_id
    LEFT JOIN privatescan.product_types  pt  ON pt.id  = p.product_type_id;

    DELETE d FROM analytics.dim_product d
    LEFT JOIN privatescan.products p ON p.id = d.product_sk
    WHERE p.id IS NULL;

    -- dim_user
    REPLACE INTO analytics.dim_user (user_sk, naam, email, actief, geladen_op)
    SELECT u.id, CONCAT(u.first_name, ' ', u.last_name), u.email, u.status, NOW()
    FROM privatescan.users u;

    DELETE d FROM analytics.dim_user d
    LEFT JOIN privatescan.users u ON u.id = d.user_sk
    WHERE u.id IS NULL;

    -- dim_pipeline_stage
    -- Verloren IDs: 5, 12, 15, 29, 38, 47 (uit PipelineStage enum)
    -- Gewonnen IDs: 4, 11, 14, 28, 37, 46
    -- afdeling: lead_pipeline_id 1/3/6 = Privatescan, 2/4/7 = Herniapoli, 5 = tech (NULL)
    -- status_categorie: alleen order-fasen, exact PipelineStage::statusCategory()
    --   option     -> 30, 31 (PS) / 39, 40 (Hernia)
    --   nearly_won -> 33-36 (PS) / 42-45 (Hernia)
    --   won        -> 37 (PS) / 46 (Hernia)
    --   lost       -> 38 (PS) / 47 (Hernia)
    REPLACE INTO analytics.dim_pipeline_stage
        (stage_sk, naam, code, is_verloren, is_gewonnen, is_order_pipeline,
         afdeling, status_categorie, geladen_op)
    SELECT
        lps.id,
        lps.name,
        lps.code,
        lps.id IN (5, 12, 15, 29, 38, 47)  AS is_verloren,
        lps.id IN (4, 11, 14, 28, 37, 46)  AS is_gewonnen,
        lps.lead_pipeline_id IN (6, 7)      AS is_order_pipeline,
        CASE
            WHEN lps.lead_pipeline_id IN (1, 3, 6) THEN 'Privatescan'
            WHEN lps.lead_pipeline_id IN (2, 4, 7) THEN 'Herniapoli'
            ELSE NULL
        END                                 AS afdeling,
        CASE
            WHEN lps.id IN (30, 31, 39, 40)                 THEN 'option'
            WHEN lps.id IN (33, 34, 35, 36, 42, 43, 44, 45) THEN 'nearly_won'
            WHEN lps.id IN (37, 46)                         THEN 'won'
            WHEN lps.id IN (38, 47)                         THEN 'lost'
            ELSE NULL
        END                                 AS status_categorie,
        NOW()
    FROM privatescan.lead_pipeline_stages lps;

    DELETE d FROM analytics.dim_pipeline_stage d
    LEFT JOIN privatescan.lead_pipeline_stages lps ON lps.id = d.stage_sk
    WHERE lps.id IS NULL;

    -- ========================================================
    -- HULPTABELLEN (per order geaggregeerd)
    -- ========================================================

    -- Vroegste geplande niet-verloren resource-slot per order (firstExaminationCarbon fallback).
    DROP TEMPORARY TABLE IF EXISTS tmp_slot;
    CREATE TEMPORARY TABLE tmp_slot (order_id BIGINT PRIMARY KEY, vroegste DATETIME) ENGINE=MEMORY
    SELECT oi.order_id, MIN(roi.`from`) AS vroegste
    FROM   privatescan.resource_orderitem roi
    JOIN   privatescan.order_items oi ON oi.id = roi.orderitem_id AND oi.status <> 'LOST'
    GROUP  BY oi.order_id;

    -- Betaalstatus-aggregaat: netto ontvangen bedrag per order.
    -- Spiegelt Order::netReceivedAmount(): refunds met paid_at zijn definitief (aftrekken),
    -- refunds zonder paid_at zijn nog niet uitbetaald (negeren). Zie app/Models/Order.php.
    DROP TEMPORARY TABLE IF EXISTS tmp_order_payments;
    CREATE TEMPORARY TABLE tmp_order_payments (order_id BIGINT PRIMARY KEY, ontvangen DECIMAL(12,2)) ENGINE=MEMORY
    SELECT
        order_id,
        SUM(CASE
                WHEN type = 'refund' AND paid_at IS NOT NULL THEN -amount
                WHEN type = 'refund' AND paid_at IS NULL     THEN 0
                ELSE amount
            END) AS ontvangen
    FROM privatescan.order_payments
    GROUP BY order_id;

    -- Exact moment waarop een order de order-verloren-fase inging (stage 38 Privatescan /
    -- 47 Hernia, PipelineStage::getLostOrderStageIds()), uit de activities-audittrail.
    -- OrderObserver::logFieldChanges() schrijft bij elke pipeline_stage_id-wijziging een
    -- system-activity met additional->>'$.attribute' = 'Status' en additional->>'$.new.value'
    -- = nieuwe stage-id. MIN(created_at) = eerste keer dat de order verloren ging (kan
    -- meermaals heen-en-weer zijn gewisseld). Orders zonder matchende activity (verloren
    -- vóór deze logging bestond) krijgen NULL -- dat is verwacht, geen bug.
    DROP TEMPORARY TABLE IF EXISTS tmp_lost_stage_at;
    CREATE TEMPORARY TABLE tmp_lost_stage_at (order_id BIGINT PRIMARY KEY, verloren_op DATETIME) ENGINE=MEMORY
    SELECT
        order_id,
        MIN(created_at) AS verloren_op
    FROM privatescan.activities
    WHERE type = 'system'
        AND order_id IS NOT NULL
        AND additional->>'$.attribute' = 'Status'
        AND additional->>'$.new.value' REGEXP '^[0-9]+$'   -- OrderItemObserver logt ook 'Status' met new/won/lost
        AND CAST(additional->>'$.new.value' AS UNSIGNED) IN (38, 47)
    GROUP BY order_id;

    -- Inkoop + regelaantallen per order.
    DROP TEMPORARY TABLE IF EXISTS tmp_order_regels;
    CREATE TEMPORARY TABLE tmp_order_regels (
        order_id  BIGINT PRIMARY KEY,
        inkoop    DECIMAL(12,2),
        n_totaal  INT,
        n_actief  INT
    ) ENGINE=MEMORY
    SELECT
        oi.order_id,
        SUM(CASE WHEN oi.status <> 'LOST' THEN COALESCE(pp.purchase_price, 0) ELSE 0 END) AS inkoop,
        COUNT(*)                                                                          AS n_totaal,
        SUM(oi.status <> 'LOST')                                                          AS n_actief
    FROM privatescan.order_items oi
    LEFT JOIN privatescan.purchase_prices pp
        ON  pp.priceable_type = 'order_items'   -- morph-alias (Relation::morphMap), niet de FQCN
        AND pp.priceable_id   = oi.id
        AND pp.type           = 'main'
    GROUP BY oi.order_id;

    -- ========================================================
    -- FEITEN
    -- ========================================================

    -- fact_orders (order-grain) — ook orders zonder orderregels.
    REPLACE INTO analytics.fact_orders
        (order_sk, ordernummer, order_titel, naam, verkoper_sk, stage_sk,
         afdeling, status_categorie, is_verloren, is_gewonnen, lost_reason,
         bron, campagne, landing_page, attribution_url, is_zakelijk, organisatie,
         verkoopdatum_sk, gesloten_datum_sk, verloren_at, doorloop_dagen, eerste_onderzoek_datum_sk, eerste_onderzoek_at,
         verkoopprijs, inkoopprijs, marge, betaalstatus, aantal_regels, aantal_regels_actief, geladen_op)
    SELECT
        o.id,
        o.order_number,
        o.title,
        sl.name,
        o.user_id,
        o.pipeline_stage_id,
        ds.afdeling,
        ds.status_categorie,
        COALESCE(ds.is_verloren, 0),
        COALESCE(ds.is_gewonnen, 0),
        o.lost_reason,
        lsrc.name,
        (SELECT mc.name
         FROM   privatescan.lead_marketing_data lmd
         JOIN   privatescan.marketing_campaigns mc ON mc.external_id = lmd.value
         WHERE  lmd.lead_id = sl.lead_id AND lmd.key = 'campaign_id'
         ORDER  BY lmd.id DESC LIMIT 1),
        (SELECT SUBSTRING_INDEX(lmd.value, '?', 1) FROM privatescan.lead_marketing_data lmd
         WHERE lmd.lead_id = sl.lead_id AND lmd.key = 'landing_page' ORDER BY lmd.id DESC LIMIT 1),
        (SELECT lmd.value FROM privatescan.lead_marketing_data lmd
         WHERE lmd.lead_id = sl.lead_id AND lmd.key = 'attribution_url' ORDER BY lmd.id DESC LIMIT 1),
        COALESCE(o.is_business, 0),
        org.name,
        DATE(o.created_at),
        o.closed_at,
        lsa.verloren_op,
        IF(o.created_at >= '2026-05-16', DATEDIFF(o.closed_at, o.created_at), NULL),  -- vóór livegang: closed_at gemigreerd = +30d
        COALESCE(o.first_examination_at, DATE(sl_slot.vroegste)),
        CASE
            WHEN o.first_examination_at IS NOT NULL OR sl_slot.vroegste IS NOT NULL
            THEN TIMESTAMP(
                     COALESCE(o.first_examination_at, DATE(sl_slot.vroegste)),
                     COALESCE(NULLIF(o.first_examination_time, ''), TIME(sl_slot.vroegste), '00:00:00')
                 )
            ELSE NULL
        END,
        COALESCE(o.total_price, 0),
        COALESCE(r.inkoop, 0),
        COALESCE(o.total_price, 0) - COALESCE(r.inkoop, 0),
        CASE
            WHEN COALESCE(o.total_price, 0) <= 0           THEN 'niet_van_toepassing'
            WHEN COALESCE(op.ontvangen, 0) <= 0             THEN 'niet_betaald'
            WHEN op.ontvangen > COALESCE(o.total_price, 0)  THEN 'credit'
            WHEN op.ontvangen >= COALESCE(o.total_price, 0) THEN 'volledig_betaald'
            ELSE 'gedeeltelijk_betaald'
        END,
        COALESCE(r.n_totaal, 0),
        COALESCE(r.n_actief, 0),
        NOW()
    FROM privatescan.orders o
    LEFT JOIN privatescan.salesleads       sl      ON sl.id      = o.sales_lead_id
    LEFT JOIN privatescan.leads            l       ON l.id       = sl.lead_id
    LEFT JOIN privatescan.lead_sources     lsrc     ON lsrc.id   = l.lead_source_id
    LEFT JOIN privatescan.organizations    org      ON org.id    = o.organization_id
    LEFT JOIN analytics.dim_pipeline_stage ds      ON ds.stage_sk = o.pipeline_stage_id
    LEFT JOIN tmp_slot                     sl_slot ON sl_slot.order_id = o.id
    LEFT JOIN tmp_order_regels             r       ON r.order_id  = o.id
    LEFT JOIN tmp_order_payments            op      ON op.order_id = o.id
    LEFT JOIN tmp_lost_stage_at             lsa     ON lsa.order_id = o.id;

    DELETE f FROM analytics.fact_orders f
    LEFT JOIN privatescan.orders o ON o.id = f.order_sk
    WHERE o.id IS NULL;

    -- fact_leads (lead-grain) — hele funnel, ook leads die nooit een order werden.
    -- Marketing-kolommen komen uit lead_marketing_data (EAV key/value, key = InboundLeadPayloadMapper::extractMarketingData()).
    -- campagne: uitsluitend via key='campaign_id' → marketing_campaigns.name. Geen fallback op de
    -- vrije-tekst key='campaign' (UTM) — oude leads zonder campaign_id zijn hersteld via
    -- `php artisan leads:repair-campaign-links` (App\Console\Commands\RepairLeadCampaignLinks),
    -- nieuwe leads horen campaign_id altijd gezet te hebben. Eén bron van waarheid, zelfde als de CRM-view.
    REPLACE INTO analytics.fact_leads
        (lead_sk, naam, lead_id, verkoper_sk, stage_sk, afdeling, status_categorie,
         is_verloren, is_gewonnen, bron, lead_type, campagne, landing_page, attribution_url,
         lost_reason, beschrijving, aangemaakt_datum_sk, gesloten_datum_sk, doorloop_dagen, geladen_op)
    SELECT
        sl.id,
        sl.name,
        sl.lead_id,
        sl.user_id,
        sl.pipeline_stage_id,
        ds.afdeling,
        ds.status_categorie,
        COALESCE(ds.is_verloren, 0),
        COALESCE(ds.is_gewonnen, 0),
        lsrc.name,
        lt.name,
        (SELECT mc.name
         FROM   privatescan.lead_marketing_data lmd
         JOIN   privatescan.marketing_campaigns mc ON mc.external_id = lmd.value
         WHERE  lmd.lead_id = sl.lead_id AND lmd.key = 'campaign_id'
         ORDER  BY lmd.id DESC LIMIT 1),
        (SELECT SUBSTRING_INDEX(lmd.value, '?', 1) FROM privatescan.lead_marketing_data lmd
         WHERE lmd.lead_id = sl.lead_id AND lmd.key = 'landing_page' ORDER BY lmd.id DESC LIMIT 1),
        (SELECT lmd.value FROM privatescan.lead_marketing_data lmd
         WHERE lmd.lead_id = sl.lead_id AND lmd.key = 'attribution_url' ORDER BY lmd.id DESC LIMIT 1),
        sl.lost_reason,
        sl.description,
        DATE(sl.created_at),
        sl.closed_at,
        IF(sl.created_at >= '2026-05-16', DATEDIFF(sl.closed_at, sl.created_at), NULL),  -- vóór livegang: closed_at gemigreerd = +30d
        NOW()
    FROM privatescan.salesleads sl
    LEFT JOIN analytics.dim_pipeline_stage ds   ON ds.stage_sk = sl.pipeline_stage_id
    LEFT JOIN privatescan.leads            l    ON l.id  = sl.lead_id
    LEFT JOIN privatescan.lead_sources     lsrc ON lsrc.id = l.lead_source_id
    LEFT JOIN privatescan.lead_types       lt   ON lt.id  = l.lead_type_id;

    DELETE f FROM analytics.fact_leads f
    LEFT JOIN privatescan.salesleads sl ON sl.id = f.lead_sk
    WHERE sl.id IS NULL;

    -- fact_order_items (regel-grain)
    REPLACE INTO analytics.fact_order_items
        (order_item_sk, order_id, ordernummer, product_sk, user_sk, stage_sk,
         verkoopdatum_sk, gesloten_datum_sk, quantity, verkoopprijs, inkoopprijs,
         is_verloren, geladen_op)
    SELECT
        oi.id,
        o.id,
        o.order_number,
        oi.product_id,
        o.user_id,
        o.pipeline_stage_id,
        DATE(o.created_at),
        o.closed_at,
        COALESCE(oi.quantity, 1),
        COALESCE(oi.total_price, 0),
        pp.purchase_price,
        oi.status = 'LOST',
        NOW()
    FROM privatescan.order_items oi
    JOIN privatescan.orders o ON o.id = oi.order_id
    LEFT JOIN privatescan.purchase_prices pp
        ON  pp.priceable_type = 'order_items'
        AND pp.priceable_id   = oi.id
        AND pp.type           = 'main';

    DELETE f FROM analytics.fact_order_items f
    LEFT JOIN privatescan.order_items oi ON oi.id = f.order_item_sk
    WHERE oi.id IS NULL;

    -- fact_planning (resource-slot-grain)
    REPLACE INTO analytics.fact_planning
        (planning_sk, order_id, order_item_sk, ordernummer, product_sk,
         resource_id, resource_naam, stage_sk, afdeling, van, tot, van_datum_sk,
         duur_minuten, regel_verloren, geladen_op)
    SELECT
        roi.id,
        oi.order_id,
        oi.id,
        o.order_number,
        oi.product_id,
        r.id,
        r.name,
        o.pipeline_stage_id,
        ds.afdeling,
        roi.`from`,
        roi.`to`,
        DATE(roi.`from`),
        TIMESTAMPDIFF(MINUTE, roi.`from`, roi.`to`),
        oi.status = 'LOST',
        NOW()
    FROM privatescan.resource_orderitem roi
    JOIN privatescan.order_items oi ON oi.id = roi.orderitem_id
    JOIN privatescan.orders      o  ON o.id  = oi.order_id
    LEFT JOIN privatescan.resources        r  ON r.id  = roi.resource_id
    LEFT JOIN analytics.dim_pipeline_stage ds ON ds.stage_sk = o.pipeline_stage_id;

    DELETE f FROM analytics.fact_planning f
    LEFT JOIN privatescan.resource_orderitem roi ON roi.id = f.planning_sk
    WHERE roi.id IS NULL;

    -- fact_hernia_traject (Herniapoli-sale-grain, cohort = ter beoordeling aangeboden)
    -- Fase-historie = aanmaakfase + elke Status-wijziging uit activities (SalesLeadObserver).
    -- Gematcht op additional->>'$.attribute' = 'Status', niet op de titel "Status gewijzigd".
    --   aanmaakfase    = old.value van de eerste Status-wijziging, anders huidige fase; tijdstip =
    --                    created_at. De aanmaak zelf wordt (net als bij leads/orders) niet gelogd.
    --   ter beoordeling = eerste verblijf in 17 dat NIET direct naar 16 (MRI via Privatescan)
    --                    gaat, of binnenkomst in 18-23 zonder via 17 te komen (overgeslagen).
    --                    MIN() → heen-en-weer (18 → 17) telt één keer.
    --   beoordeeld     = eerste fase 18-28 daarna; ingepland = eerste fase 23/25/26/27 daarna.
    -- Fase-ids hard (pipeline 4): ids zijn vast, zie docblock App\Enums\PipelineStage. Nieuwe Hernia-salesfase → hier indelen.
    DELETE FROM analytics.fact_hernia_traject;

    REPLACE INTO analytics.fact_hernia_traject  -- REPLACE: tolerant voor een gelijktijdige sync (event + handmatige CALL)
        (lead_sk, naam, verkoper_sk, stage_sk, ter_beoordeling_at, ter_beoordeling_maand,
         beoordeeld_at, ingepland_at, is_beoordeeld, is_ingepland, mri_herkomst,
         behandeling_type, behandeling_soort, uitkomst_beoordeling, operatieadvies, aanvullend_onderzoek,
         uitkomst, reden_niet_ingepland, lost_reason, geladen_op)
    WITH hs AS (
        SELECT s.* FROM privatescan.salesleads s
        JOIN privatescan.lead_pipeline_stages st ON st.id = s.pipeline_stage_id AND st.lead_pipeline_id = 4
    ),
    ev AS (
        SELECT a.sales_lead_id AS sl_id, a.id AS seq, CAST(a.created_at AS DATETIME) AS at,  -- TIMESTAMP → NULL-MIN() wordt anders 0000-00-00
               IF(a.additional->>'$.old.value' REGEXP '^[0-9]+$', CAST(a.additional->>'$.old.value' AS UNSIGNED), NULL) AS old_stage,
               IF(a.additional->>'$.new.value' REGEXP '^[0-9]+$', CAST(a.additional->>'$.new.value' AS UNSIGNED), NULL) AS new_stage
        FROM privatescan.activities a
        JOIN hs ON hs.id = a.sales_lead_id
        WHERE a.type = 'system' AND a.additional->>'$.attribute' = 'Status'
          AND a.additional->>'$.new.value' REGEXP '^[0-9]+$'   -- strict mode: geen CAST op rommel
    ),
    stays AS (
        SELECT hs.id AS sl_id, 0 AS seq, CAST(hs.created_at AS DATETIME) AS at,
               COALESCE((SELECT e.old_stage FROM ev e WHERE e.sl_id = hs.id ORDER BY e.at, e.seq LIMIT 1),
                        hs.pipeline_stage_id) AS stage
        FROM hs
        UNION ALL
        SELECT sl_id, seq, at, new_stage FROM ev
    ),
    fases AS (
        SELECT sl_id, at, stage,
               LAG(stage)  OVER w AS prev_stage,
               LEAD(stage) OVER w AS next_stage
        FROM stays WINDOW w AS (PARTITION BY sl_id ORDER BY at, seq)
    ),
    tb AS (
        SELECT sl_id, MIN(at) AS ter_beoordeling_at
        FROM fases
        WHERE (stage = 17 AND COALESCE(next_stage, 0) <> 16)
           OR (stage BETWEEN 18 AND 23 AND COALESCE(prev_stage, 0) NOT BETWEEN 17 AND 28)
        GROUP BY sl_id
    ),
    mijlpaal AS (
        SELECT tb.sl_id, tb.ter_beoordeling_at,
               MIN(CASE WHEN f.stage BETWEEN 18 AND 28 AND f.at >= tb.ter_beoordeling_at THEN f.at END) AS beoordeeld_at,
               MIN(CASE WHEN f.stage IN (23, 25, 26, 27) AND f.at >= tb.ter_beoordeling_at THEN f.at END) AS ingepland_at,
               MAX(f.stage = 16) AS ooit_16  -- 16 = Onderzoek via Privatescan (MRI intern)
        FROM tb JOIN fases f ON f.sl_id = tb.sl_id
        GROUP BY tb.sl_id, tb.ter_beoordeling_at
    ),
    regels AS (
        SELECT o.sales_lead_id AS sl_id,
               MAX(dp.hoofd_groep = 'Onderzoeken' AND dp.naam LIKE 'MRI%') AS heeft_mri,
               SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN dp.hoofd_groep = 'Behandelingen' THEN dp.product_groep END ORDER BY oi.id SEPARATOR '|'), '|', 1) AS behandeling_type,
               SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN dp.hoofd_groep = 'Behandelingen' THEN pg2.name END ORDER BY oi.id SEPARATOR '|'), '|', 1) AS behandeling_soort
        FROM privatescan.orders o
        JOIN privatescan.order_items oi ON oi.order_id = o.id AND oi.status <> 'LOST'
        JOIN analytics.dim_product dp ON dp.product_sk = oi.product_id
        JOIN privatescan.products p ON p.id = oi.product_id
        LEFT JOIN privatescan.product_groups pg1 ON pg1.id = p.product_group_id
        LEFT JOIN privatescan.product_groups pg2 ON pg2.id = pg1.parent_id
        WHERE o.sales_lead_id IN (SELECT id FROM hs)
        GROUP BY o.sales_lead_id
    )
    SELECT
        hs.id,
        hs.name,
        hs.user_id,
        hs.pipeline_stage_id,
        m.ter_beoordeling_at,
        DATE_FORMAT(m.ter_beoordeling_at, '%Y-%m'),
        m.beoordeeld_at,
        m.ingepland_at,
        m.beoordeeld_at IS NOT NULL,
        m.ingepland_at IS NOT NULL,
        IF(COALESCE(r.heeft_mri, 0) OR m.ooit_16, 'Intern', 'Extern'),
        r.behandeling_type,
        r.behandeling_soort,
        ao.label,  -- privatescan.assessment_outcomes (beheerd in CRM-instellingen)
        ao.is_surgery_advice,
        COALESCE(hs.additional_research_required, 0),
        CASE
            WHEN m.ingepland_at IS NOT NULL     THEN 'Ingepland'
            WHEN m.beoordeeld_at IS NULL        THEN IF(hs.pipeline_stage_id = 29, 'Afgehaakt voor beoordeling', 'Wacht op beoordeling')
            WHEN hs.pipeline_stage_id = 29      THEN 'Verloren'
            WHEN hs.pipeline_stage_id = 28      THEN 'Afgerond zonder behandeling'
            ELSE 'Open'
        END,
        CASE
            WHEN m.ingepland_at IS NOT NULL THEN NULL
            WHEN hs.pipeline_stage_id = 29 THEN CASE hs.lost_reason
                WHEN 'geenMRI' THEN '(nog) geen MRI'
                WHEN 'afval' THEN 'Afval'
                WHEN 'prijs' THEN 'Prijs'
                WHEN 'geen_vergoeding_zorgv' THEN 'Geen vergoeding verzekeraar'
                WHEN 'afstand' THEN 'Afstand'
                WHEN 'informatief' THEN 'Puur informatief'
                WHEN 'prescan' THEN 'Naar Prescan'
                WHEN 'ziek' THEN 'Ziek'
                WHEN 'concurrent' THEN 'Naar Concurrent'
                WHEN 'niet_planbaar' THEN 'Niet planbaar'
                WHEN 'geen_vervoer' THEN 'Geen vervoer'
                WHEN 'partner_niet' THEN 'Partner niet akkoord'
                WHEN 'niet_uitvoerbaar' THEN 'Niet uitvoerbaar'
                WHEN 'negatief_advies' THEN 'Negatief advies Privatescan'
                WHEN 'geen_reactie' THEN 'Geen reactie meer'
                WHEN 'betaalt_niet' THEN 'Betaalt niet'
                WHEN 'verslapen' THEN 'Verslapen'
                WHEN 'te_laat' THEN 'Te laat'
                WHEN 'angst' THEN 'Angst'
                WHEN 'ontevreden' THEN 'Ontevreden'
                WHEN 'elders_nl_standaard' THEN 'Kan in NL zorg terecht'
                WHEN 'uitstel_omstandigheden' THEN 'Uitstel door omstandigheden'
                WHEN 'nieuwsbrief' THEN 'Alleen nieuwsbriefinschrijving'
                WHEN 'foutief' THEN 'Foutief'
                WHEN 'datainvoer' THEN 'Data invoer achteraf'
                WHEN 'geen_reden' THEN 'Geen reden'
                WHEN 'Spoort_niet' THEN 'Spoort niet'
                WHEN 'Onjuiste actie interne medewerker' THEN 'Onjuiste actie interne medewerker'
                ELSE 'Geen reden opgegeven'
            END
            ELSE st.name
        END,
        hs.lost_reason,
        NOW()
    FROM hs
    JOIN mijlpaal m ON m.sl_id = hs.id
    JOIN privatescan.lead_pipeline_stages st ON st.id = hs.pipeline_stage_id
    LEFT JOIN regels r ON r.sl_id = hs.id
    LEFT JOIN privatescan.assessment_outcomes ao ON ao.code = hs.assessment_outcome;

    -- fact_aanvragen (Krayin-lead-grain) — zie 001_schema.sql
    REPLACE INTO analytics.fact_aanvragen
        (aanvraag_sk, verkoper_sk, stage_sk, afdeling, status_categorie, aangemaakt_at, aangemaakt_datum_sk,
         omgezet_at, gesloten_at, uren_tot_sales, uren_tot_gesloten, geladen_op)
    SELECT
        l.id,
        l.user_id,
        l.lead_pipeline_stage_id,
        ds.afdeling,
        CASE WHEN ds.is_gewonnen THEN 'won' WHEN ds.is_verloren THEN 'lost' ELSE 'open' END,
        l.created_at,
        DATE(l.created_at),
        s.omgezet_at,
        l.closed_at,
        TIMESTAMPDIFF(HOUR, l.created_at, s.omgezet_at),
        IF(l.created_at >= '2026-05-16', TIMESTAMPDIFF(HOUR, l.created_at, l.closed_at), NULL),  -- legacy-aanvragen zijn bij livegang in bulk gesloten
        NOW()
    FROM privatescan.leads l
    LEFT JOIN (SELECT lead_id, MIN(created_at) AS omgezet_at FROM privatescan.salesleads GROUP BY lead_id) s ON s.lead_id = l.id
    LEFT JOIN analytics.dim_pipeline_stage ds ON ds.stage_sk = l.lead_pipeline_stage_id
    WHERE l.created_at IS NOT NULL AND l.deleted_at IS NULL;

    DELETE f FROM analytics.fact_aanvragen f
    LEFT JOIN privatescan.leads l ON l.id = f.aanvraag_sk AND l.deleted_at IS NULL
    WHERE l.id IS NULL;

    -- fact_activiteiten (call/task-grain) — zie 001_schema.sql
    REPLACE INTO analytics.fact_activiteiten
        (activiteit_sk, type, gebruiker_sk, afdeling, is_afgerond, aangemaakt_at, aangemaakt_datum_sk,
         gepland_tot, afgerond_at, uren_doorloop, is_op_tijd, geladen_op)
    SELECT
        a.id,
        a.type,
        a.user_id,
        COALESCE(dso.afdeling, dss.afdeling, dsl.afdeling),
        a.is_done,
        a.created_at,
        DATE(a.created_at),
        a.schedule_to,
        x.afgerond_at,
        TIMESTAMPDIFF(HOUR, a.created_at, x.afgerond_at),
        IF(x.afgerond_at IS NULL OR a.schedule_to IS NULL, NULL, DATE(x.afgerond_at) <= DATE(a.schedule_to)),
        NOW()
    FROM privatescan.activities a
    CROSS JOIN LATERAL (SELECT IF(a.is_done, COALESCE(a.completed_at, a.updated_at), NULL) AS afgerond_at) x
    LEFT JOIN privatescan.orders     o  ON o.id  = a.order_id
    LEFT JOIN privatescan.salesleads sl ON sl.id = a.sales_lead_id
    LEFT JOIN privatescan.leads      l  ON l.id  = a.lead_id
    LEFT JOIN analytics.dim_pipeline_stage dso ON dso.stage_sk = o.pipeline_stage_id
    LEFT JOIN analytics.dim_pipeline_stage dss ON dss.stage_sk = sl.pipeline_stage_id
    LEFT JOIN analytics.dim_pipeline_stage dsl ON dsl.stage_sk = l.lead_pipeline_stage_id
    WHERE a.type IN ('call', 'task') AND a.created_at IS NOT NULL;

    DELETE f FROM analytics.fact_activiteiten f
    LEFT JOIN privatescan.activities a ON a.id = f.activiteit_sk
    WHERE a.id IS NULL;

    DROP TEMPORARY TABLE IF EXISTS tmp_slot;
    DROP TEMPORARY TABLE IF EXISTS tmp_order_regels;
END$$

DELIMITER ;
