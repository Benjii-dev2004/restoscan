-- ================================================================
-- RESTOSCAN — Migration Phase 2
-- A executer sur une BDD ayant deja la Phase 1
-- ================================================================

-- Tracking des emails envoyes pour eviter les doublons
ALTER TABLE `restaurants`
    ADD COLUMN `email_30j_sent`    DATETIME NULL AFTER `gerant_telephone`,
    ADD COLUMN `email_7j_sent`     DATETIME NULL AFTER `email_30j_sent`,
    ADD COLUMN `email_expire_sent` DATETIME NULL AFTER `email_7j_sent`;

-- Logs des actions super-admin
CREATE TABLE IF NOT EXISTS `super_admin_logs` (
    `id`                   INT          NOT NULL AUTO_INCREMENT,
    `super_admin_id`       INT          NOT NULL,
    `action`               VARCHAR(50)  NOT NULL,
    `target_restaurant_id` INT          NULL,
    `details`              TEXT         NULL,
    `ip`                   VARCHAR(45)  NULL,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sa`     (`super_admin_id`),
    KEY `idx_target` (`target_restaurant_id`),
    KEY `idx_date`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
