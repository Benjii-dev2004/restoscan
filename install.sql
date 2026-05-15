-- ================================================================
-- RESTOSCAN — install.sql
-- Schéma complet + données de test
-- Exécuter UNE SEULE FOIS pour initialiser la base de données
-- ================================================================

CREATE DATABASE IF NOT EXISTS `restoscan`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `restoscan`;

-- ─── Tables ───────────────────────────────────────────────────────

CREATE TABLE `tables` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `numero`     INT          NOT NULL,
    `qr_token`   VARCHAR(64)  NOT NULL,
    `capacite`   INT          NOT NULL DEFAULT 4,
    `statut`     ENUM('libre','occupee','reservee') NOT NULL DEFAULT 'libre',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_qr_token` (`qr_token`),
    UNIQUE KEY `uq_numero`   (`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Catégories ───────────────────────────────────────────────────

CREATE TABLE `categories` (
    `id`    INT          NOT NULL AUTO_INCREMENT,
    `nom`   VARCHAR(100) NOT NULL,
    `ordre` INT          NOT NULL DEFAULT 0,
    `icone` VARCHAR(50)  NOT NULL DEFAULT 'utensils',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Plats du menu ────────────────────────────────────────────────

CREATE TABLE `menu_items` (
    `id`                INT            NOT NULL AUTO_INCREMENT,
    `categorie_id`      INT            NOT NULL,
    `nom`               VARCHAR(150)   NOT NULL,
    `description`       TEXT,
    `prix`              DECIMAL(8,2)   NOT NULL,
    `image`             VARCHAR(255)   NOT NULL DEFAULT '',
    `disponible`        TINYINT(1)     NOT NULL DEFAULT 1,
    `temps_preparation` INT            NOT NULL DEFAULT 15,
    PRIMARY KEY (`id`),
    KEY `fk_menu_categorie` (`categorie_id`),
    CONSTRAINT `fk_menu_categorie`
        FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Commandes ────────────────────────────────────────────────────

CREATE TABLE `commandes` (
    `id`         INT            NOT NULL AUTO_INCREMENT,
    `table_id`   INT            NOT NULL,
    `statut`     ENUM('en_attente','en_preparation','pret','servi','annule')
                               NOT NULL DEFAULT 'en_attente',
    `total`      DECIMAL(8,2)   NOT NULL DEFAULT 0.00,
    `notes`      TEXT,
    `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_cmd_table` (`table_id`),
    KEY `idx_cmd_statut` (`statut`),
    KEY `idx_cmd_date`   (`created_at`),
    CONSTRAINT `fk_cmd_table`
        FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Articles de commande ──────────────────────────────────────────

CREATE TABLE `commande_items` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `commande_id`   INT          NOT NULL,
    `menu_item_id`  INT          NOT NULL,
    `quantite`      INT          NOT NULL DEFAULT 1,
    `prix_unitaire` DECIMAL(8,2) NOT NULL,
    `notes`         TEXT,
    PRIMARY KEY (`id`),
    KEY `fk_ci_commande`  (`commande_id`),
    KEY `fk_ci_menu_item` (`menu_item_id`),
    CONSTRAINT `fk_ci_commande`
        FOREIGN KEY (`commande_id`)  REFERENCES `commandes`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ci_menu_item`
        FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Utilisateurs ────────────────────────────────────────────────

CREATE TABLE `users` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `nom`        VARCHAR(100) NOT NULL,
    `email`      VARCHAR(150) NOT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `role`       ENUM('admin','cuisine','serveur') NOT NULL DEFAULT 'serveur',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ================================================================
-- DONNÉES DE TEST
-- ================================================================

-- ─── Catégories ───────────────────────────────────────────────────
INSERT INTO `categories` (`nom`, `ordre`, `icone`) VALUES
    ('Entrées',   1, 'salad'),
    ('Plats',     2, 'utensils'),
    ('Grillades', 3, 'fire'),
    ('Desserts',  4, 'ice-cream'),
    ('Boissons',  5, 'wine-glass');

-- ─── Plats ────────────────────────────────────────────────────────
INSERT INTO `menu_items` (`categorie_id`, `nom`, `description`, `prix`, `disponible`, `temps_preparation`) VALUES
    -- Entrées (1)
    (1, 'Salade César',        'Romaine, parmesan, croûtons, sauce césar maison',   2500,  1, 5),
    (1, 'Soupe du jour',       'Soupe de légumes frais, servie avec pain grillé',   1500,  1, 8),
    (1, 'Nems au poulet',      '4 rouleaux de printemps frits, sauce sweet chili',  2000,  1, 10),
    (1, 'Avocat crevettes',    'Avocat frais, crevettes grillées, vinaigrette',      3000,  1, 7),

    -- Plats (2)
    (2, 'Poulet braisé',       'Demi-poulet braisé aux épices, servi avec attiéké', 5500,  1, 20),
    (2, 'Riz sauté aux légumes', 'Riz cantonnais aux légumes et œufs',              3500,  1, 15),
    (2, 'Tilapia frit',        'Tilapia entier frit, sauce tomate, alloco',          6000,  1, 25),
    (2, 'Kedjenou de poulet',  'Poulet mijoté au gingembre et piment, riz blanc',   6500,  1, 30),
    (2, 'Pasta bolognaise',    'Pâtes fraîches, sauce viande et tomates',            4500,  1, 18),

    -- Grillades (3)
    (3, 'Brochettes bœuf',     '4 brochettes de bœuf marinées, sauce grillée',      5000,  1, 15),
    (3, 'Côtes de porc',       'Côtes grillées au barbecue, frites maison',          7000,  1, 25),
    (3, 'Crevettes grillées',  '8 crevettes géantes, beurre citron ail',            8000,  1, 20),

    -- Desserts (4)
    (4, 'Fondant chocolat',    'Coulant au chocolat noir, boule de vanille',         2000,  1, 12),
    (4, 'Salade de fruits',    'Fruits de saison frais, sirop de menthe',            1500,  1, 5),
    (4, 'Crème brûlée',        'Recette traditionnelle française',                   2500,  1, 5),

    -- Boissons (5)
    (5, 'Bissap',              'Boisson à l\'hibiscus, sucre, menthe fraîche — 50cl', 1000, 1, 2),
    (5, 'Jus de gingembre',    'Jus frais de gingembre et citron — 50cl',             1000, 1, 2),
    (5, 'Eau minérale',        'Bouteille 50cl',                                       500, 1, 1),
    (5, 'Coca-Cola',           'Canette 33cl bien fraîche',                            800, 1, 1),
    (5, 'Café espresso',       'Double expresso',                                     1000, 1, 3);

-- ─── Tables ───────────────────────────────────────────────────────
INSERT INTO `tables` (`numero`, `qr_token`, `capacite`, `statut`) VALUES
    (1,  'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2', 2, 'libre'),
    (2,  'b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3', 4, 'libre'),
    (3,  'c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4', 4, 'libre'),
    (4,  'd4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5', 6, 'libre'),
    (5,  'e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6', 4, 'libre'),
    (6,  'f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7', 8, 'libre'),
    (7,  'a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8', 2, 'libre'),
    (8,  'b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9', 4, 'libre'),
    (9,  'c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0', 4, 'libre'),
    (10, 'd0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1', 6, 'libre');

-- ─── Utilisateurs (mots de passe = "password" hashé) ──────────────
-- Généré avec password_hash('password', PASSWORD_BCRYPT)
INSERT INTO `users` (`nom`, `email`, `password`, `role`) VALUES
    ('Admin Principal', 'admin@restoscan.com',
     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
    ('Chef Jean',       'cuisine@restoscan.com',
     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cuisine'),
    ('Serveur Marie',   'serveur@restoscan.com',
     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'serveur');

-- ================================================================
-- FIN D'INSTALLATION
-- Les URLs de test des QR codes :
--   Table 1 : http://localhost/restoscan/menu/a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2
--   ...etc
-- Connexion admin : admin@restoscan.com / password
-- Connexion cuisine : cuisine@restoscan.com / password
-- ================================================================
