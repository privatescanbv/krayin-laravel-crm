-- =========================================================
-- analytics 001: schema, dimensietabellen, feitentabel, watermark
-- Idempotent — veilig om meerdere keren uit te voeren
-- Uitvoeren: docker-compose exec mysql_crm mysql -u root -p < database/analytics/001_schema.sql
-- =========================================================

CREATE DATABASE IF NOT EXISTS analytics
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- ---- Watermark: houdt laatste synchronisatietijdstip bij per tabel ----
CREATE TABLE IF NOT EXISTS analytics.sync_watermark (
    tabel_naam   VARCHAR(100) NOT NULL,
    laatste_sync TIMESTAMP    NOT NULL DEFAULT '2020-01-01 00:00:00',
    PRIMARY KEY (tabel_naam)
) ENGINE=InnoDB;

INSERT IGNORE INTO analytics.sync_watermark (tabel_naam)
VALUES ('fact_order_items');

-- ---- dim_date: kalenderattributen per dag ----
CREATE TABLE IF NOT EXISTS analytics.dim_date (
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
CREATE TABLE IF NOT EXISTS analytics.dim_product (
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
CREATE TABLE IF NOT EXISTS analytics.dim_user (
    user_sk    INT          NOT NULL,
    naam       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NULL,
    actief     BOOLEAN      NOT NULL DEFAULT 1,
    geladen_op TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_sk)
) ENGINE=InnoDB;

-- ---- dim_pipeline_stage: pipelinefasen met gewonnen/verloren markering ----
CREATE TABLE IF NOT EXISTS analytics.dim_pipeline_stage (
    stage_sk         INT          NOT NULL,
    naam             VARCHAR(255) NOT NULL,
    code             VARCHAR(100) NOT NULL,
    is_verloren      BOOLEAN      NOT NULL,
    is_gewonnen      BOOLEAN      NOT NULL,
    is_order_pipeline BOOLEAN     NOT NULL COMMENT '1 = Order-pipeline (id 6 PS / id 7 Hernia)',
    geladen_op       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (stage_sk)
) ENGINE=InnoDB;

-- ---- fact_order_items: één rij per orderregel ----
CREATE TABLE IF NOT EXISTS analytics.fact_order_items (
    order_item_sk     BIGINT        NOT NULL,
    order_id          BIGINT        NOT NULL,
    ordernummer       VARCHAR(9)    NULL,
    product_sk        INT           NOT NULL,
    user_sk           INT           NULL     COMMENT 'Verkoper via orders.user_id',
    stage_sk          INT           NULL,
    verkoopdatum_sk   DATE          NOT NULL COMMENT 'DATE(orders.created_at)',
    gesloten_datum_sk DATE          NULL     COMMENT 'orders.closed_at (nullable)',
    quantity          INT           NOT NULL DEFAULT 1,
    verkoopprijs      DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'order_items.total_price',
    inkoopprijs       DECIMAL(10,2) NULL     COMMENT 'purchase_prices.purchase_price type=main',
    is_verloren       BOOLEAN       NOT NULL COMMENT 'order_items.status = LOST',
    bijgewerkt_op     TIMESTAMP     NOT NULL,
    PRIMARY KEY (order_item_sk),
    INDEX idx_verkoopdatum (verkoopdatum_sk),
    INDEX idx_user         (user_sk),
    INDEX idx_product      (product_sk),
    INDEX idx_stage        (stage_sk),
    INDEX idx_bijgewerkt   (bijgewerkt_op)
) ENGINE=InnoDB;
