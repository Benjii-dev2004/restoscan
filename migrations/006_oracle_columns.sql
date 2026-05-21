-- ================================================================
-- RESTOSCAN — Migration 006 : Colonnes Oracle Simphony sur restaurants
--
-- Ajoute les champs necessaires a l integration Oracle.
-- Chaque resto peut etre en mode 'standalone' (sans Oracle) ou 'oracle'.
-- Les credentials API Oracle sont specifiques a chaque resto (multi-tenant).
-- ================================================================

ALTER TABLE `restaurants`
    ADD COLUMN `mode_integration`           ENUM('standalone','oracle') NOT NULL DEFAULT 'standalone'  AFTER `formule`,
    ADD COLUMN `oracle_org_short_name`      VARCHAR(100) NULL  AFTER `mode_integration`,
    ADD COLUMN `oracle_loc_ref`             VARCHAR(100) NULL  AFTER `oracle_org_short_name`,
    ADD COLUMN `oracle_rvc_ref`             VARCHAR(100) NULL  AFTER `oracle_loc_ref`,
    ADD COLUMN `oracle_api_username`        VARCHAR(150) NULL  AFTER `oracle_rvc_ref`,
    ADD COLUMN `oracle_api_password_enc`    TEXT         NULL  AFTER `oracle_api_username`,
    ADD COLUMN `oracle_id_token`            TEXT         NULL  AFTER `oracle_api_password_enc`,
    ADD COLUMN `oracle_refresh_token`       TEXT         NULL  AFTER `oracle_id_token`,
    ADD COLUMN `oracle_token_expires_at`    DATETIME     NULL  AFTER `oracle_refresh_token`,
    ADD COLUMN `oracle_password_expires_at` DATETIME     NULL  AFTER `oracle_token_expires_at`,
    ADD COLUMN `oracle_menu_id`             VARCHAR(100) NULL  AFTER `oracle_password_expires_at`,
    ADD COLUMN `oracle_last_sync`           DATETIME     NULL  AFTER `oracle_menu_id`;

CREATE INDEX `idx_mode_integration` ON `restaurants` (`mode_integration`);
