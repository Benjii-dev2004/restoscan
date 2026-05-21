<?php
/**
 * config/oracle_config.example.php
 *
 * ROLE : Template de configuration pour l integration Oracle Simphony STS Gen2.
 *        A copier en `oracle_config.php` (gitignore) puis remplir avec les
 *        vraies valeurs fournies par Oracle.
 *
 * SECURITE :
 *   - oracle_config.php NE DOIT JAMAIS etre commite (deja dans .gitignore)
 *   - Les credentials API specifiques a chaque restaurant sont stockes en BDD
 *     (table restaurants, colonnes oracle_*) et NON dans ce fichier
 *   - Ce fichier ne contient que des parametres GLOBAUX a l installation
 */

// ─── URLs Oracle ────────────────────────────────────────────────────────────
// URL de base de l API Oracle Simphony Transaction Services Gen2
// En production : https://api.simphony.oracleindustry.com
// En dev local  : http://localhost:8081  (le mock que nous fournissons)
define('ORACLE_API_BASE_URL', 'http://localhost:8081');

// URL du serveur OAuth2 (identite/sso). Souvent meme host que l API.
define('ORACLE_AUTH_URL',     'http://localhost:8081');

// ─── Identifiant client OAuth2 (fourni par Oracle a l onboarding) ──────────
// Le client_id est partage pour toute l installation, les credentials user
// changent eux par restaurant
define('ORACLE_CLIENT_ID',    'restoscan-client-id-here');

// ─── Parametres reseau ─────────────────────────────────────────────────────
// Timeout des requetes Oracle en secondes (Oracle peut etre lent en pic)
define('ORACLE_TIMEOUT',         30);
// Nombre de retries automatiques en cas d echec reseau (back-off croissant)
define('ORACLE_RETRY_ATTEMPTS',  3);

// ─── Cle de chiffrement AES-256 ────────────────────────────────────────────
// CRITIQUE : utilisee pour chiffrer/dechiffrer oracle_api_password_enc en BDD
// Generer avec : openssl rand -hex 32  (64 caracteres hex = 256 bits)
// NE JAMAIS partager, NE JAMAIS commiter. Unique par installation.
define('ORACLE_ENCRYPTION_KEY', 'remplacer-par-32-octets-hex-generated-by-openssl-rand');

// ─── Webhook de notifications entrantes ────────────────────────────────────
// Cle secrete partagee avec Oracle pour valider les signatures des webhooks
// Generer une cle aleatoire et la fournir a Oracle lors de la configuration
define('ORACLE_WEBHOOK_SECRET', 'remplacer-par-une-cle-secrete-pour-le-webhook');

// ─── Mode debug ────────────────────────────────────────────────────────────
// true : enregistre TOUT (utile en dev). false : ne log que les erreurs.
define('ORACLE_DEBUG', defined('ENV') && ENV === 'development');
