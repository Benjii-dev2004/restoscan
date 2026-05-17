<?php
/**
 * index.php
 * Point d'entrée unique (Front Controller) de RESTOSCAN
 * Rôle : initialiser l'application, charger les dépendances, dispatcher les routes
 */

// ─── Chargement de la configuration ──────────────────────────────────────────
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// ─── Chargement du core MVC ───────────────────────────────────────────────────
require_once __DIR__ . '/core/Context.php';
require_once __DIR__ . '/core/Model.php';
require_once __DIR__ . '/core/View.php';
require_once __DIR__ . '/core/Controller.php';
require_once __DIR__ . '/core/Router.php';

// ─── Session ──────────────────────────────────────────────────────────────────
session_name(SESSION_NAME);
// SEC-15 : secure uniquement si connexion HTTPS active
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Générer le token CSRF s'il n'existe pas encore
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

// ─── Définition des routes ────────────────────────────────────────────────────
$router = new Router();

// Interface Client (publique)
$router->get('/menu/{qr_token}',      'MenuController@show');
$router->post('/order/create',         'OrderController@create');
$router->get('/order/status/{id}',    'OrderController@status');

// Interface Cuisine
$router->get('/kitchen',              'KitchenController@index');
$router->post('/kitchen/update/{id}', 'KitchenController@update');
$router->get('/kitchen/poll',         'KitchenController@poll');

// Interface Serveur
$router->get('/waiter',               'WaiterController@index');
$router->post('/waiter/serve/{id}',   'WaiterController@serve');
$router->get('/waiter/poll',          'WaiterController@poll');

// Authentification
$router->get('/auth/login',           'AuthController@loginForm');
$router->post('/auth/login',          'AuthController@login');
$router->post('/auth/logout',         'AuthController@logout');

// Back-office Admin
$router->get('/admin/dashboard',              'AdminController@dashboard');
$router->get('/admin/menu',                   'AdminController@menuList');
$router->post('/admin/menu/add',              'AdminController@menuAdd');
$router->post('/admin/menu/edit/{id}',        'AdminController@menuEdit');
$router->post('/admin/menu/delete/{id}',      'AdminController@menuDelete');
$router->post('/admin/menu/toggle/{id}',      'AdminController@menuToggle');
$router->get('/admin/tables',                 'AdminController@tablesList');
$router->post('/admin/tables/add',            'AdminController@tablesAdd');
$router->post('/admin/tables/delete/{id}',    'AdminController@tablesDelete');
$router->get('/admin/tables/qrcode/{id}',     'AdminController@tablesQrcode');
$router->post('/admin/tables/qrcache/clear',  'AdminController@tablesQrCacheClear');
$router->get('/admin/orders',                 'AdminController@ordersList');
$router->get('/admin/users',                  'AdminController@usersList');
$router->post('/admin/users/add',             'AdminController@usersAdd');
$router->post('/admin/users/delete/{id}',     'AdminController@usersDelete');
$router->get('/admin/categories',             'AdminController@categoriesList');
$router->post('/admin/categories/add',        'AdminController@categoriesAdd');
$router->post('/admin/categories/delete/{id}','AdminController@categoriesDelete');
$router->get('/admin/settings',               'AdminController@settingsForm');
$router->post('/admin/settings/save',         'AdminController@settingsSave');
$router->get('/admin/settings/detect-ip',     'AdminController@settingsDetectIp');

// Super Admin (proprietaire RESTOSCAN)
$router->get('/superadmin/login',                    'SuperAdminController@loginForm');
$router->post('/superadmin/login',                   'SuperAdminController@login');
$router->post('/superadmin/logout',                  'SuperAdminController@logout');
$router->get('/superadmin/dashboard',                'SuperAdminController@dashboard');
$router->get('/superadmin/restaurant/new',           'SuperAdminController@restaurantNewForm');
$router->post('/superadmin/restaurant/create',       'SuperAdminController@restaurantCreate');
$router->post('/superadmin/restaurant/extend/{id}',  'SuperAdminController@restaurantExtend');
$router->post('/superadmin/restaurant/toggle/{id}',  'SuperAdminController@restaurantToggle');
$router->post('/superadmin/restaurant/delete/{id}',      'SuperAdminController@restaurantDelete');
$router->post('/superadmin/restaurant/impersonate/{id}', 'SuperAdminController@restaurantImpersonate');
$router->get('/superadmin/stop-impersonation',           'SuperAdminController@stopImpersonation');
$router->get('/superadmin/logs',                         'SuperAdminController@logs');

// Page d'accueil → rediriger vers login si pas auth
$router->get('/', 'HomeController@index');

// ─── Dispatcher ───────────────────────────────────────────────────────────────
$router->dispatch();
