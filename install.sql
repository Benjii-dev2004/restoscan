-- ================================================================
-- RESTOSCAN — install.sql (Phase 1 SaaS multi-tenant)
-- Schéma complet + données de test pour DEV LOCAL (WAMP)
-- ================================================================

CREATE DATABASE IF NOT EXISTS `restoscan`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `restoscan`;

-- ─── Restaurants (table maitre du multi-tenant) ───────────────────
CREATE TABLE `restaurants` (
    `id`                          INT          NOT NULL AUTO_INCREMENT,
    `nom`                         VARCHAR(150) NOT NULL,
    `slug`                        VARCHAR(80)  NOT NULL,
    `abonnement_debut`            DATETIME     NULL,
    `abonnement_fin`              DATETIME     NULL,
    `statut`                      ENUM('actif','suspendu','expire') NOT NULL DEFAULT 'actif',
    `formule`                     ENUM('starter','pro','premium')   NOT NULL DEFAULT 'starter',
    `mode_integration`            ENUM('standalone','oracle')       NOT NULL DEFAULT 'standalone',
    `oracle_org_short_name`       VARCHAR(100) NULL,
    `oracle_loc_ref`              VARCHAR(100) NULL,
    `oracle_rvc_ref`              VARCHAR(100) NULL,
    `oracle_api_username`         VARCHAR(150) NULL,
    `oracle_api_password_enc`     TEXT         NULL,
    `oracle_id_token`             TEXT         NULL,
    `oracle_refresh_token`        TEXT         NULL,
    `oracle_token_expires_at`     DATETIME     NULL,
    `oracle_password_expires_at`  DATETIME     NULL,
    `oracle_menu_id`              VARCHAR(100) NULL,
    `oracle_last_sync`            DATETIME     NULL,
    `gerant_email`                VARCHAR(150) NULL,
    `gerant_telephone`            VARCHAR(30)  NULL,
    `email_30j_sent`              DATETIME     NULL,
    `email_7j_sent`               DATETIME     NULL,
    `email_expire_sent`           DATETIME     NULL,
    `created_at`                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`),
    KEY `idx_mode_integration` (`mode_integration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Logs des actions super-admin ─────────────────────────────────
CREATE TABLE `super_admin_logs` (
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

-- ─── Login attempts (brute force persistant en BDD) ───────────────
CREATE TABLE `login_attempts` (
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

-- ─── Rate limits (anti-DoS sur endpoints publics) ─────────────────
CREATE TABLE `rate_limits` (
    `rl_key`       VARCHAR(150) NOT NULL,
    `hits`         INT          NOT NULL DEFAULT 0,
    `window_start` DATETIME     NOT NULL,
    PRIMARY KEY (`rl_key`),
    KEY `idx_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Oracle Simphony logs (audit complet des appels API) ──────────
CREATE TABLE `oracle_logs` (
    `id`             BIGINT       NOT NULL AUTO_INCREMENT,
    `restaurant_id`  INT          NOT NULL,
    `operation`      VARCHAR(100) NOT NULL,
    `endpoint`       VARCHAR(255) NULL,
    `method`         VARCHAR(10)  NULL,
    `request_body`   LONGTEXT     NULL,
    `response_code`  INT          NULL,
    `response_body`  LONGTEXT     NULL,
    `duration_ms`    INT          NULL,
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

-- ─── Super admins (toi, le proprietaire de RESTOSCAN) ─────────────
CREATE TABLE `super_admins` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `nom`        VARCHAR(100) NOT NULL,
    `email`      VARCHAR(150) NOT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sa_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Tables (par restaurant) ──────────────────────────────────────
CREATE TABLE `tables` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `restaurant_id` INT          NOT NULL,
    `numero`        INT          NOT NULL,
    `qr_token`      VARCHAR(64)  NOT NULL,
    `capacite`      INT          NOT NULL DEFAULT 4,
    `statut`        ENUM('libre','occupee','reservee') NOT NULL DEFAULT 'libre',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_qr_token`     (`qr_token`),
    UNIQUE KEY `uq_resto_numero` (`restaurant_id`, `numero`),
    KEY `idx_resto` (`restaurant_id`),
    CONSTRAINT `fk_tables_resto`
        FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Categories ───────────────────────────────────────────────────
CREATE TABLE `categories` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `restaurant_id` INT          NOT NULL,
    `nom`           VARCHAR(100) NOT NULL,
    `ordre`         INT          NOT NULL DEFAULT 0,
    `icone`         VARCHAR(50)  NOT NULL DEFAULT 'utensils',
    PRIMARY KEY (`id`),
    KEY `idx_resto` (`restaurant_id`),
    CONSTRAINT `fk_cat_resto`
        FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Plats du menu ────────────────────────────────────────────────
CREATE TABLE `menu_items` (
    `id`                INT            NOT NULL AUTO_INCREMENT,
    `restaurant_id`     INT            NOT NULL,
    `categorie_id`      INT            NOT NULL,
    `nom`               VARCHAR(150)   NOT NULL,
    `description`       TEXT,
    `prix`              DECIMAL(8,2)   NOT NULL,
    `image`             VARCHAR(255)   NOT NULL DEFAULT '',
    `disponible`        TINYINT(1)     NOT NULL DEFAULT 1,
    `temps_preparation` INT            NOT NULL DEFAULT 15,
    PRIMARY KEY (`id`),
    KEY `idx_resto`     (`restaurant_id`),
    KEY `fk_menu_categorie` (`categorie_id`),
    CONSTRAINT `fk_menu_resto`
        FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_menu_categorie`
        FOREIGN KEY (`categorie_id`)  REFERENCES `categories` (`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Commandes ────────────────────────────────────────────────────
CREATE TABLE `commandes` (
    `id`            INT            NOT NULL AUTO_INCREMENT,
    `restaurant_id` INT            NOT NULL,
    `numero_local`  INT            NOT NULL DEFAULT 0,
    `date_jour`     DATE           NOT NULL,
    `table_id`      INT            NOT NULL,
    `statut`        ENUM('en_attente','en_preparation','pret','servi','annule')
                                   NOT NULL DEFAULT 'en_attente',
    `total`         DECIMAL(8,2)   NOT NULL DEFAULT 0.00,
    `notes`         TEXT,
    `created_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_resto`            (`restaurant_id`),
    KEY `idx_resto_jour_numero`(`restaurant_id`, `date_jour`, `numero_local`),
    KEY `fk_cmd_table`         (`table_id`),
    KEY `idx_cmd_statut`       (`statut`),
    KEY `idx_cmd_date`         (`created_at`),
    CONSTRAINT `fk_cmd_resto`
        FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cmd_table`
        FOREIGN KEY (`table_id`)      REFERENCES `tables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Articles de commande (scope via commandes) ───────────────────
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
        FOREIGN KEY (`commande_id`)  REFERENCES `commandes`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ci_menu_item`
        FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Utilisateurs (par restaurant) ────────────────────────────────
CREATE TABLE `users` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `restaurant_id` INT          NOT NULL,
    `nom`           VARCHAR(100) NOT NULL,
    `email`         VARCHAR(150) NOT NULL,
    `password`      VARCHAR(255) NOT NULL,
    `role`          ENUM('admin','cuisine','serveur') NOT NULL DEFAULT 'serveur',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`),
    KEY `idx_resto` (`restaurant_id`),
    CONSTRAINT `fk_user_resto`
        FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Parametres (par restaurant) ──────────────────────────────────
CREATE TABLE `settings` (
    `restaurant_id` INT          NOT NULL,
    `cle`           VARCHAR(100) NOT NULL,
    `valeur`        TEXT         NOT NULL,
    PRIMARY KEY (`restaurant_id`, `cle`),
    CONSTRAINT `fk_settings_resto`
        FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ================================================================
-- DONNEES DE TEST (DEV LOCAL UNIQUEMENT)
-- ================================================================

-- ─── Restaurant pilote ────────────────────────────────────────────
INSERT INTO `restaurants` (`id`, `nom`, `slug`, `abonnement_debut`, `abonnement_fin`, `statut`, `formule`, `gerant_email`) VALUES
    (1, 'Mon Restaurant', 'mon-restaurant', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 'actif', 'premium', 'gerant@monrestaurant.com');

-- ─── Super admin (mot de passe = "Restoscan2024!") ────────────────
INSERT INTO `super_admins` (`nom`, `email`, `password`) VALUES
    ('Super Admin', 'super@restoscan.com',
     '$2y$12$y0gpc6rc0StnplxJAN7CS.EsEoETLDZCrPJ4Rjzcy3aZjjQqoorzO');

-- ─── Settings du restaurant 1 ─────────────────────────────────────
INSERT INTO `settings` (`restaurant_id`, `cle`, `valeur`) VALUES
    (1, 'nom_restaurant',     'Mon Restaurant'),
    (1, 'slogan',             'Bienvenue a notre table'),
    (1, 'couleur_principale', '#e85d04'),
    (1, 'devise',             'FCFA'),
    (1, 'ip_locale',          '');

-- ─── Categories du restaurant 1 ───────────────────────────────────
INSERT INTO `categories` (`restaurant_id`, `nom`, `ordre`, `icone`) VALUES
    (1, 'Entrées',   1, 'salad'),
    (1, 'Plats',     2, 'utensils'),
    (1, 'Grillades', 3, 'fire'),
    (1, 'Desserts',  4, 'ice-cream'),
    (1, 'Boissons',  5, 'wine-glass');

-- ─── Plats ────────────────────────────────────────────────────────
INSERT INTO `menu_items` (`restaurant_id`, `categorie_id`, `nom`, `description`, `prix`, `disponible`, `temps_preparation`) VALUES
    (1, 1, 'Salade César',        'Romaine, parmesan, croûtons, sauce césar maison',   2500, 1, 5),
    (1, 1, 'Soupe du jour',       'Soupe de légumes frais, servie avec pain grillé',   1500, 1, 8),
    (1, 1, 'Nems au poulet',      '4 rouleaux de printemps frits, sauce sweet chili',  2000, 1, 10),
    (1, 2, 'Poulet braisé',       'Demi-poulet braisé aux épices, servi avec attiéké', 5500, 1, 20),
    (1, 2, 'Tilapia frit',        'Tilapia entier frit, sauce tomate, alloco',         6000, 1, 25),
    (1, 3, 'Brochettes bœuf',     '4 brochettes de bœuf marinées, sauce grillée',      5000, 1, 15),
    (1, 4, 'Fondant chocolat',    'Coulant au chocolat noir, boule de vanille',        2000, 1, 12),
    (1, 5, 'Bissap',              'Boisson à l\'hibiscus — 50cl',                       1000, 1, 2),
    (1, 5, 'Coca-Cola',           'Canette 33cl bien fraîche',                          800, 1, 1);

-- ─── Tables du restaurant 1 ───────────────────────────────────────
INSERT INTO `tables` (`restaurant_id`, `numero`, `qr_token`, `capacite`, `statut`) VALUES
    (1, 1, 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2', 2, 'libre'),
    (1, 2, 'b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3', 4, 'libre'),
    (1, 3, 'c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4', 4, 'libre'),
    (1, 4, 'd4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5', 6, 'libre');

-- ─── Comptes du restaurant 1 (mots de passe = "password") ─────────
INSERT INTO `users` (`restaurant_id`, `nom`, `email`, `password`, `role`) VALUES
    (1, 'Admin Principal', 'admin@restoscan.com',
     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
    (1, 'Chef Jean',       'cuisine@restoscan.com',
     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cuisine'),
    (1, 'Serveur Marie',   'serveur@restoscan.com',
     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'serveur');

-- ================================================================
-- Connexion super-admin : super@restoscan.com / Restoscan2024!
-- Connexion admin       : admin@restoscan.com / password
-- ================================================================
