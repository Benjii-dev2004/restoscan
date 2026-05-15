<?php
/**
 * config/config.php
 * Constantes globales de l'application RESTOSCAN
 * Role : centraliser toute la configuration (BDD, URL, app)
 */

// ─── Environnement ───────────────────────────────────────────────────────────
// En production sur Alwaysdata, passer a 'production'
define('ENV', getenv('APP_ENV') ?: 'development');

// ─── Base de données ─────────────────────────────────────────────────────────
// Variables d'environnement en priorite (production), fallback local (dev)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'restoscan');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ─── Application ─────────────────────────────────────────────────────────────
define('APP_NAME', 'RESTOSCAN');
define('APP_VERSION', '1.0.0');

// ─── URL de base — dynamique selon l'acces (PC ou telephone) ────────────────
// Supporte HTTP et HTTPS, local et heberge
$_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

// En production Alwaysdata le sous-dossier /restoscan n'existe pas (racine du site)
// En local WAMP il faut le sous-dossier
$_subdir = (ENV === 'production') ? '' : '/restoscan';
define('BASE_URL', $_scheme . '://' . $_host . $_subdir);

// ─── Chemins absolus ─────────────────────────────────────────────────────────
define('ROOT_PATH',    dirname(__DIR__));
define('APP_PATH',     ROOT_PATH . '/app');
define('PUBLIC_PATH',  ROOT_PATH . '/public');
define('QRCODE_PATH',  ROOT_PATH . '/qrcodes');
define('QRCODE_URL',   BASE_URL . '/qrcodes');

// ─── Session ─────────────────────────────────────────────────────────────────
define('SESSION_NAME', 'restoscan_session');
define('SESSION_LIFETIME', 7200); // 2 heures

// ─── Securite ─────────────────────────────────────────────────────────────────
define('CSRF_TOKEN_NAME', '_csrf_token');

// ─── Gestion des erreurs ─────────────────────────────────────────────────────
if (ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
}
