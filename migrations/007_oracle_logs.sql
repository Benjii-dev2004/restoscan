-- ================================================================
-- RESTOSCAN — Migration 007 : Table de logs Oracle
--
-- Tracer TOUTES les interactions avec l API Oracle (succes ET echecs).
-- Indispensable pour debugger en production chez un grand hotel.
--
-- Les colonnes sensibles (passwords, tokens en clair) ne doivent JAMAIS
-- y entrer (assurer par OracleLog::sanitizePayload()).
-- ================================================================

CREATE TABLE IF NOT EXISTS `oracle_logs` (
    `id`             BIGINT       NOT NULL AUTO_INCREMENT,
    `restaurant_id`  INT          NOT NULL,
    `operation`      VARCHAR(100) NOT NULL,                -- ex: 'auth.token', 'menu.summary', 'check.create'
    `endpoint`       VARCHAR(255) NULL,                    -- URL appelee
    `method`         VARCHAR(10)  NULL,                    -- GET, POST, etc
    `request_body`   LONGTEXT     NULL,                    -- payload envoye (sanitize)
    `response_code`  INT          NULL,                    -- code HTTP recu
    `response_body`  LONGTEXT     NULL,                    -- payload recu (tronque si > 64ko)
    `duration_ms`    INT          NULL,                    -- duree d execution
    `success`        TINYINT(1)   NOT NULL DEFAULT 0,
    `error_message`  TEXT         NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_resto`     (`restaurant_id`),
    KEY `idx_created`   (`created_at`),
    KEY `idx_operation` (`operation`),
    KEY `idx_success`   (`success`),
    CONSTRAINT `fk_oracle_logs_resto`
        FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
