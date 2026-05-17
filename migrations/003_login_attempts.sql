-- ================================================================
-- RESTOSCAN — Migration 003 : Login attempts persistant
-- Corrige la vulnerabilite "brute force counter bypass via cookie clearing"
-- ================================================================

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           INT          NOT NULL AUTO_INCREMENT,
    `ip`           VARCHAR(45)  NOT NULL,
    `scope`        ENUM('user','superadmin') NOT NULL DEFAULT 'user',
    `attempts`     INT          NOT NULL DEFAULT 0,
    `locked_until` DATETIME     NULL,
    `last_attempt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ip_scope` (`ip`, `scope`),
    KEY `idx_locked_until` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
