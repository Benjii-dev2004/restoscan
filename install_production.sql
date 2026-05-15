-- ================================================================
-- RESTOSCAN — install_production.sql
-- Pour Alwaysdata (ou tout hebergeur) : la BDD existe deja
-- Importer via phpMyAdmin de l'hebergeur
-- NE PAS executer en local (utiliser install.sql a la place)
-- ================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ─── Tables ───────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `tables` (
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

-- ─── Categories ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `categories` (
    `id`    INT          NOT NULL AUTO_INCREMENT,
    `nom`   VARCHAR(100) NOT NULL,
    `ordre` INT          NOT NULL DEFAULT 0,
    `icone` VARCHAR(50)  NOT NULL DEFAULT 'utensils',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Plats du menu ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `menu_items` (
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

CREATE TABLE IF NOT EXISTS `commandes` (
    `id`         INT            NOT NULL AUTO_INCREMENT,
    `table_id`   INT            NOT NULL,
    `statut`     ENUM('en_attente','en_preparation','pret','servi','annule')
                               NOT NULL DEFAULT 'en_attente',
    `total`      DECIMAL(8,2)   NOT NULL DEFAULT 0.00,
    `notes`      TEXT,
    `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_cmd_table`   (`table_id`),
    KEY `idx_cmd_statut` (`statut`),
    KEY `idx_cmd_date`   (`created_at`),
    CONSTRAINT `fk_cmd_table`
        FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Articles de commande ──────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `commande_items` (
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
        FOREIGN KEY (`commande_id`)  REFERENCES `commandes`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ci_menu_item`
        FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Utilisateurs ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `nom`        VARCHAR(100) NOT NULL,
    `email`      VARCHAR(150) NOT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `role`       ENUM('admin','cuisine','serveur') NOT NULL DEFAULT 'serveur',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Parametres ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `settings` (
    `cle`    VARCHAR(100) NOT NULL,
    `valeur` TEXT         NOT NULL,
    PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`cle`, `valeur`) VALUES
    ('nom_restaurant',    'Mon Restaurant'),
    ('slogan',            'Bienvenue a notre table'),
    ('couleur_principale','#e85d04'),
    ('devise',            'FCFA'),
    ('ip_locale',         '')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);

-- ─── Compte admin initial ─────────────────────────────────────────
-- MOT DE PASSE PAR DEFAUT : Restoscan2024!
-- CHANGER IMMEDIATEMENT apres la premiere connexion !
INSERT IGNORE INTO `users` (`nom`, `email`, `password`, `role`) VALUES
    ('Administrateur', 'admin@monrestaurant.com',
     '$2y$12$y0gpc6rc0StnplxJAN7CS.EsEoETLDZCrPJ4Rjzcy3aZjjQqoorzO', 'admin');

-- ================================================================
-- Connexion : admin@monrestaurant.com / Restoscan2024!
-- CHANGER l'email et le mot de passe dans Admin > Utilisateurs
-- ================================================================
