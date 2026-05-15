-- Migration : table settings + paramètres par défaut
-- Exécuter une seule fois dans phpMyAdmin

USE `restoscan`;

CREATE TABLE IF NOT EXISTS `settings` (
    `cle`    VARCHAR(100) NOT NULL,
    `valeur` TEXT         NOT NULL,
    PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`cle`, `valeur`) VALUES
    ('nom_restaurant',    'Mon Restaurant'),
    ('slogan',            'Bienvenue à notre table'),
    ('couleur_principale','#e85d04'),
    ('devise',            'FCFA')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);
