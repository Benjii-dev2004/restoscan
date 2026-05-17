-- ================================================================
-- RESTOSCAN — Migration 004 : Rate limits
-- Empeche le spam sur /order/create (si un QR token fuit)
-- ================================================================

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `rl_key`       VARCHAR(150) NOT NULL,
    `hits`         INT          NOT NULL DEFAULT 0,
    `window_start` DATETIME     NOT NULL,
    PRIMARY KEY (`rl_key`),
    KEY `idx_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
