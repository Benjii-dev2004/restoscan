-- ================================================================
-- RESTOSCAN — Migration 005 : Numero local par restaurant + reset quotidien
--
-- L ID auto-increment est global (toutes commandes confondues).
-- On ajoute numero_local + date_jour pour que chaque restaurant ait
-- sa propre sequence #1, #2, #3... qui repart a 1 chaque jour.
-- ================================================================

ALTER TABLE `commandes`
    ADD COLUMN `numero_local` INT NOT NULL DEFAULT 0 AFTER `restaurant_id`,
    ADD COLUMN `date_jour`    DATE NULL          AFTER `numero_local`;

-- Backfill date_jour pour les commandes existantes
UPDATE `commandes` SET `date_jour` = DATE(`created_at`) WHERE `date_jour` IS NULL;

-- Backfill numero_local (sequence par resto + jour, ordonnee par created_at)
-- Necessite MySQL 8+ (ROW_NUMBER)
UPDATE `commandes` c
JOIN (
    SELECT
        id,
        ROW_NUMBER() OVER (PARTITION BY restaurant_id, DATE(created_at) ORDER BY created_at) AS rn
    FROM `commandes`
) AS r ON c.id = r.id
SET c.numero_local = r.rn;

-- Rendre date_jour NOT NULL maintenant que tout est backfille
ALTER TABLE `commandes` MODIFY `date_jour` DATE NOT NULL;

-- Index pour acceleration des SELECT MAX(numero_local) du jour
CREATE INDEX `idx_resto_jour_numero` ON `commandes` (`restaurant_id`, `date_jour`, `numero_local`);
