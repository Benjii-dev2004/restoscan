<?php
/**
 * config/config.php
 * Configuration RESTOSCAN
 * En production : les vraies valeurs sont dans config.local.php (non committe)
 * En local : valeurs par defaut ci-dessous
 */

// ─── Valeurs par defaut (developpement local) ────────────────────────────────
$_db_host = 'localhost';
$_db_name = 'restoscan';
$_db_user = 'root';
$_db_pass = '';
$_env     = 'development';

// ─── Override production (config.local.php n'est pas dans git) ───────────────
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// ─── Constantes ──────────────────────────────────────────────────────────────
define('ENV',     $_env);
define('DB_HOST', $_db_host);
define('DB_NAME', $_db_name);
define('DB_USER', $_db_user);
define('DB_PASS', $_db_pass);

// ─── Application ─────────────────────────────────────────────────────────────
define('APP_NAME',    'RESTOSCAN');
define('APP_VERSION', '1.0.0');

// ─── URL de base ─────────────────────────────────────────────────────────────
$_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_subdir = (ENV === 'production') ? '' : '/restoscan';
define('BASE_URL', $_scheme . '://' . $_host . $_subdir);

// ─── Chemins absolus ─────────────────────────────────────────────────────────
define('ROOT_PATH',   dirname(__DIR__));
define('APP_PATH',    ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('QRCODE_PATH', ROOT_PATH . '/qrcodes');
define('QRCODE_URL',  BASE_URL  . '/qrcodes');

// ─── Session ─────────────────────────────────────────────────────────────────
define('SESSION_NAME',     'restoscan_session');
define('SESSION_LIFETIME', 7200);

// ─── Securite ────────────────────────────────────────────────────────────────
define('CSRF_TOKEN_NAME', '_csrf_token');

// ─── Erreurs ─────────────────────────────────────────────────────────────────
if (ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
