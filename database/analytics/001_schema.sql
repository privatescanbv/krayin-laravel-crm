-- =========================================================
-- analytics 001: schema (dimensies + feiten)
--
-- Alle analytics-tabellen worden bij elke sync VOLLEDIG herladen
-- (zie 003_sync_procedure.sql). Ze bevatten dus geen eigen historie —
-- daarom is DROP + CREATE hier veilig en houdt het schema exact gelijk
-- aan dit bestand. Draai 001 t/m 006 in volgorde.
--
-- Grain-overzicht:
--   fact_orders        1 rij per order        — omzetrapporten (per maand / per medewerker)
--   fact_order_items   1 rij per orderregel   — productanalyse
--   fact_planning      1 rij per resource-slot — onderzoekdatum / capaciteit
--
-- Uitvoeren: docker compose exec -T mysql_crm mysql -uroot -p < database/analytics/001_schema.sql
-- =========================================================

CREATE DATABASE IF NOT EXISTS analytics
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Legacy: incrementele sync met watermark is vervangen door full-reload.
DROP TABLE IF EXISTS analytics.sync_watermark;

DROP TABLE IF EXISTS analytics.fact_planning;
DROP TABLE IF EXISTS analytics.fact_order_items;
DROP TABLE IF EXISTS analytics.fact_orders;
DROP TABLE IF EXISTS analytics.dim_pipeline_stage;
DROP TABLE IF EXISTS analytics.dim_user;
DROP TABLE IF EXISTS analytics.dim_product;
DROP TABLE IF EXISTS analytics.dim_date;

-- ---- dim_date: kalenderattributen per dag ----
CREATE TABLE analytics.dim_date (
    date_sk      DATE        NOT NULL,
    dag_naam     VARCHAR(20) NOT NULL COMMENT 'Maandag t/m Zondag',
    dag_nummer   TINYINT     NOT NULL COMMENT '1=Maandag 7=Zondag',
    week_nummer  TINYINT     NOT NULL COMMENT 'ISO week 01-53',
    jaar_week    VARCHAR(8)  NOT NULL COMMENT 'bijv. 2026-W23',
    maand_nummer TINYINT     NOT NULL,
    maand_naam   VARCHAR(20) NOT NULL,
    kwartaal     TINYINT     NOT NULL,
    jaar         SMALLINT    NOT NULL,
    is_werkdag   BOOLEAN     NOT NULL,
    PRIMARY KEY (date_sk)
) ENGINE=InnoDB;

-- ---- dim_product: productattributen + gecentraliseerde categorie-indeling ----
CREATE TABLE analytics.dim_product (
    product_sk    INT          NOT NULL,
    naam          VARCHAR(255) NOT NULL,
    external_id   VARCHAR(255) NULL,
    categorie     VARCHAR(100) NOT NULL COMMENT 'MRI LWS / PTED operatie / Neurochirurg beoordeling / bladgroepnaam',
    is_speciaal   BOOLEAN      NOT NULL COMMENT '1 voor de 5 geselecteerde producten',
    product_type  VARCHAR(100) NULL,
    product_groep VARCHAR(100) NULL COMMENT 'Bladgroep (bijv. Lendenwervelkolom (LWS))',
    hoofd_groep   VARCHAR(100) NULL COMMENT 'Onderzoeken / Diensten / Behandelingen',
    actief        BOOLEAN      NOT NULL DEFAULT 1,
    geladen_op    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_sk)
) ENGINE=InnoDB;

-- ---- dim_user: medewerkers / verkopers ----
CREATE TABLE analytics.dim_user (
    user_sk    INT          NOT NULL,
    naam       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NULL,
    actief     BOOLEAN      NOT NULL DEFAULT 1,
    geladen_op TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_sk)
) ENGINE=InnoDB;

-- ---- dim_pipeline_stage: pipelinefasen met afdeling + statuscategorie ----
CREATE TABLE analytics.dim_pipeline_stage (
    stage_sk          INT          NOT NULL,
    naam              VARCHAR(255) NOT NULL,
    code              VARCHAR(100) NOT NULL,
    is_verloren       BOOLEAN      NOT NULL,
    is_gewonnen       BOOLEAN      NOT NULL,
    is_order_pipeline BOOLEAN      NOT NULL COMMENT '1 = Order-pipeline (id 6 PS / id 7 Hernia)',
    afdeling          VARCHAR(20)  NULL     COMMENT 'Privatescan / Herniapoli (NULL = tech)',
    status_categorie  VARCHAR(20)  NULL     COMMENT 'Alleen order-fasen: option / nearly_won / won / lost (PipelineStage::statusCategory)',
    geladen_op        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (stage_sk)
) ENGINE=InnoDB;

-- ---- fact_orders: één rij per order (order-grain) ----
-- verkoopprijs = orders.total_price (som niet-verloren regels, Order::recalculateTotalPrice)
-- inkoopprijs  = som hoofd-inkoopprijs van de niet-verloren regels (Order::totalPurchasePrice)
-- eerste_onderzoek_* = firstExaminationCarbon(): first_examination_at, of anders het
--                      vroegste niet-verloren resource-slot.
CREATE TABLE analytics.fact_orders (
    order_sk                  BIGINT        NOT NULL,
    ordernummer               VARCHAR(9)    NULL,
    order_titel               VARCHAR(255)  NULL,
    naam                      VARCHAR(255)  NULL COMMENT 'salesleads.name',
    verkoper_sk               INT           NULL COMMENT 'orders.user_id',
    stage_sk                  INT           NULL,
    afdeling                  VARCHAR(20)   NULL,
    status_categorie          VARCHAR(20)   NULL,
    is_verloren               BOOLEAN       NOT NULL DEFAULT 0 COMMENT 'fase = lost',
    is_gewonnen               BOOLEAN       NOT NULL DEFAULT 0 COMMENT 'fase = won',
    verkoopdatum_sk           DATE          NOT NULL COMMENT 'DATE(orders.created_at)',
    gesloten_datum_sk         DATE          NULL,
    eerste_onderzoek_datum_sk DATE          NULL,
    eerste_onderzoek_at       DATETIME      NULL,
    verkoopprijs              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    inkoopprijs               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    marge                     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    aantal_regels             INT           NOT NULL DEFAULT 0,
    aantal_regels_actief      INT           NOT NULL DEFAULT 0,
    geladen_op                TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (order_sk),
    INDEX idx_verkoopdatum   (verkoopdatum_sk),
    INDEX idx_onderzoekdatum (eerste_onderzoek_datum_sk),
    INDEX idx_verkoper       (verkoper_sk),
    INDEX idx_stage          (stage_sk)
) ENGINE=InnoDB;

-- ---- fact_order_items: één rij per orderregel (regel-grain, productanalyse) ----
CREATE TABLE analytics.fact_order_items (
    order_item_sk     BIGINT        NOT NULL,
    order_id          BIGINT        NOT NULL,
    ordernummer       VARCHAR(9)    NULL,
    product_sk        INT           NOT NULL,
    user_sk           INT           NULL COMMENT 'Verkoper via orders.user_id',
    stage_sk          INT           NULL,
    verkoopdatum_sk   DATE          NOT NULL COMMENT 'DATE(orders.created_at)',
    gesloten_datum_sk DATE          NULL,
    quantity          INT           NOT NULL DEFAULT 1,
    verkoopprijs      DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'order_items.total_price',
    inkoopprijs       DECIMAL(10,2) NULL     COMMENT 'purchase_prices.purchase_price type=main',
    is_verloren       BOOLEAN       NOT NULL COMMENT 'order_items.status = LOST',
    geladen_op        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (order_item_sk),
    INDEX idx_order        (order_id),
    INDEX idx_verkoopdatum (verkoopdatum_sk),
    INDEX idx_user         (user_sk),
    INDEX idx_product      (product_sk),
    INDEX idx_stage        (stage_sk)
) ENGINE=InnoDB;

-- ---- fact_planning: één rij per resource-slot (resource_orderitem) ----
CREATE TABLE analytics.fact_planning (
    planning_sk    BIGINT       NOT NULL COMMENT 'resource_orderitem.id',
    order_id       BIGINT       NOT NULL,
    order_item_sk  BIGINT       NOT NULL,
    ordernummer    VARCHAR(9)   NULL,
    product_sk     INT          NULL,
    resource_id    INT          NULL,
    resource_naam  VARCHAR(255) NULL,
    stage_sk       INT          NULL,
    afdeling       VARCHAR(20)  NULL,
    van            DATETIME     NOT NULL,
    tot            DATETIME     NULL,
    van_datum_sk   DATE         NOT NULL COMMENT 'DATE(van)',
    duur_minuten   INT          NULL     COMMENT 'TIMESTAMPDIFF(MINUTE, van, tot)',
    regel_verloren BOOLEAN      NOT NULL COMMENT 'order_items.status = LOST',
    geladen_op     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (planning_sk),
    INDEX idx_van_datum (van_datum_sk),
    INDEX idx_order     (order_id),
    INDEX idx_resource  (resource_id)
) ENGINE=InnoDB;
