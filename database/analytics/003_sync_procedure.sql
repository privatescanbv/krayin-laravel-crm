-- =========================================================
-- analytics 003: sync_all() — dimensies volledig herladen,
--                              feiten incrementeel bijwerken
-- Idempotent via DROP/CREATE
-- =========================================================

DROP PROCEDURE IF EXISTS analytics.sync_all;

DELIMITER $$

CREATE DEFINER='privatescan-analytics'@'%' PROCEDURE analytics.sync_all()
BEGIN
    DECLARE v_watermark TIMESTAMP;
    DECLARE v_nu        TIMESTAMP DEFAULT NOW();

    -- ========================================================
    -- DIMENSIES: volledig herladen (klein volume)
    -- ========================================================

    -- dim_product
    -- Categorielogica staat hier gecentraliseerd, niet in dashboards.
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

    -- dim_user
    REPLACE INTO analytics.dim_user (user_sk, naam, email, actief, geladen_op)
    SELECT
        u.id,
        CONCAT(u.first_name, ' ', u.last_name),
        u.email,
        u.status,
        NOW()
    FROM privatescan.users u;

    -- dim_pipeline_stage
    -- Verloren IDs: 5, 12, 15, 29, 38, 47 (uit PipelineStage enum)
    -- Gewonnen IDs: 4, 11, 14, 28, 37, 46
    -- Order-pipeline: lead_pipeline_id IN (6=Privatescan, 7=Hernia)
    REPLACE INTO analytics.dim_pipeline_stage
        (stage_sk, naam, code, is_verloren, is_gewonnen, is_order_pipeline, geladen_op)
    SELECT
        lps.id,
        lps.name,
        lps.code,
        lps.id IN (5, 12, 15, 29, 38, 47)   AS is_verloren,
        lps.id IN (4, 11, 14, 28, 37, 46)   AS is_gewonnen,
        lps.lead_pipeline_id IN (6, 7)       AS is_order_pipeline,
        NOW()
    FROM privatescan.lead_pipeline_stages lps;

    -- ========================================================
    -- FEITEN: incrementeel op basis van watermark
    -- Pakt alle orderregels en bijbehorende orders die zijn
    -- bijgewerkt na de laatste sync.
    -- ========================================================

    SELECT laatste_sync INTO v_watermark
    FROM   analytics.sync_watermark
    WHERE  tabel_naam = 'fact_order_items';

    INSERT INTO analytics.fact_order_items
        (order_item_sk, order_id, ordernummer, product_sk, user_sk, stage_sk,
         verkoopdatum_sk, gesloten_datum_sk, quantity, verkoopprijs,
         inkoopprijs, is_verloren, bijgewerkt_op)
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
        GREATEST(oi.updated_at, o.updated_at)
    FROM privatescan.order_items oi
    JOIN privatescan.orders o
        ON  o.id = oi.order_id
    LEFT JOIN privatescan.purchase_prices pp
        ON  pp.priceable_type = 'App\\Models\\OrderItem'
        AND pp.priceable_id   = oi.id
        AND pp.type           = 'main'
    WHERE oi.updated_at > v_watermark
       OR o.updated_at  > v_watermark
    ON DUPLICATE KEY UPDATE
        ordernummer       = VALUES(ordernummer),
        user_sk           = VALUES(user_sk),
        stage_sk          = VALUES(stage_sk),
        verkoopdatum_sk   = VALUES(verkoopdatum_sk),
        gesloten_datum_sk = VALUES(gesloten_datum_sk),
        quantity          = VALUES(quantity),
        verkoopprijs      = VALUES(verkoopprijs),
        inkoopprijs       = VALUES(inkoopprijs),
        is_verloren       = VALUES(is_verloren),
        bijgewerkt_op     = VALUES(bijgewerkt_op);

    -- Watermark bijwerken naar moment van start sync
    UPDATE analytics.sync_watermark
    SET    laatste_sync = v_nu
    WHERE  tabel_naam   = 'fact_order_items';

END$$

DELIMITER ;
