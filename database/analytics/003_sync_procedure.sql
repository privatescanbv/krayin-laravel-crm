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

    -- dim_product — categorielogica staat hier gecentraliseerd, niet in dashboards.
    -- product_groups: pg1 = bladgroep, pg2 = middengroep, pg3 = hoofdgroep
    REPLACE INTO analytics.dim_product
        (product_sk, naam, external_id, categorie, is_speciaal,
         product_type, product_groep, hoofd_groep, actief, geladen_op)
    SELECT
        p.id,
        COALESCE(p.name, 'Onbekend'),
        p.external_id,
        CASE p.external_id
            WHEN '1065' THEN 'MRI LWS'
            WHEN '1066' THEN 'MRI LWS'
            WHEN '1134' THEN 'Neurochirurg beoordeling'
            WHEN '1136' THEN 'PTED operatie'
            WHEN '1137' THEN 'PTED operatie'
            ELSE COALESCE(pg1.name, 'Overig')
        END                                                   AS categorie,
        p.external_id IN ('1065','1066','1134','1136','1137') AS is_speciaal,
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
         afdeling, status_categorie, is_verloren, is_gewonnen,
         verkoopdatum_sk, gesloten_datum_sk, eerste_onderzoek_datum_sk, eerste_onderzoek_at,
         verkoopprijs, inkoopprijs, marge, aantal_regels, aantal_regels_actief, geladen_op)
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
        DATE(o.created_at),
        o.closed_at,
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
        COALESCE(r.n_totaal, 0),
        COALESCE(r.n_actief, 0),
        NOW()
    FROM privatescan.orders o
    LEFT JOIN privatescan.salesleads       sl      ON sl.id      = o.sales_lead_id
    LEFT JOIN analytics.dim_pipeline_stage ds      ON ds.stage_sk = o.pipeline_stage_id
    LEFT JOIN tmp_slot                     sl_slot ON sl_slot.order_id = o.id
    LEFT JOIN tmp_order_regels             r       ON r.order_id  = o.id;

    DELETE f FROM analytics.fact_orders f
    LEFT JOIN privatescan.orders o ON o.id = f.order_sk
    WHERE o.id IS NULL;

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

    DROP TEMPORARY TABLE IF EXISTS tmp_slot;
    DROP TEMPORARY TABLE IF EXISTS tmp_order_regels;
END$$

DELIMITER ;
