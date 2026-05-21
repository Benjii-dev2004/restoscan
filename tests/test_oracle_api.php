<?php
/**
 * Test d integration OracleApiService contre le mock Oracle.
 *
 * Lancer :
 *   1. php -S localhost:8081 -t mock-oracle/    (dans une fenetre)
 *   2. php tests/test_oracle_api.php
 */
define('ENV', 'development');
define('APP_PATH', __DIR__ . '/../app');
define('ROOT_PATH', __DIR__ . '/..');

define('ORACLE_API_BASE_URL', 'http://localhost:8081');
define('ORACLE_AUTH_URL',     'http://localhost:8081');
define('ORACLE_CLIENT_ID',    'restoscan-test');
define('ORACLE_TIMEOUT',         30);
define('ORACLE_RETRY_ATTEMPTS',  2);
define('ORACLE_ENCRYPTION_KEY', str_repeat('a', 64));

define('DB_HOST', 'localhost');
define('DB_NAME', 'restoscan');
define('DB_USER', 'root');
define('DB_PASS', '');
define('CSRF_TOKEN_NAME', '_csrf_token');

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/core/Crypto.php';
require_once ROOT_PATH . '/core/OracleHttp.php';
require_once APP_PATH  . '/models/OracleLog.php';
require_once APP_PATH  . '/models/Restaurant.php';
require_once APP_PATH  . '/services/OracleAuthService.php';
require_once APP_PATH  . '/services/OracleApiService.php';

$pdo = Database::getInstance();

// Setup resto 1 en mode oracle
$pwd = Crypto::encrypt('test_password');
$stmt = $pdo->prepare("UPDATE restaurants SET
    mode_integration='oracle',
    oracle_org_short_name='TEST_ORG',
    oracle_loc_ref='LOC-001',
    oracle_rvc_ref='RVC-001',
    oracle_api_username='test_user',
    oracle_api_password_enc=?,
    oracle_id_token=NULL,
    oracle_refresh_token=NULL,
    oracle_token_expires_at=NULL
    WHERE id=1");
$stmt->execute([$pwd]);

function section(string $title): void {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  $title\n";
    echo str_repeat('=', 60) . "\n";
}

function check(string $label, bool $cond, string $detail = ''): void {
    $flag = $cond ? '[OK]' : '[KO]';
    echo "  $flag $label" . ($detail ? " — $detail" : '') . "\n";
    if (!$cond && $detail) echo "       /!\\ Echec : $detail\n";
}

$api = new OracleApiService(1);

// ─── MENU ─────────────────────────────────────────────────────────────────
section('MENU API');

$summary = $api->getMenuSummary();
check('getMenuSummary retourne une liste',
      isset($summary['items']) && count($summary['items']) > 0,
      'items: ' . count($summary['items'] ?? []));

$menu = $api->getMenu('menu-001');
check('getMenu("menu-001") renvoie des categories',
      isset($menu['categories']) && count($menu['categories']) > 0,
      'categories: ' . count($menu['categories'] ?? []));

$unavail = $api->getUnavailableItems();
check('getUnavailableItems',
      isset($unavail['items']),
      'unavailable: ' . count($unavail['items'] ?? []));

$discounts = $api->getDiscounts();
check('getDiscounts', isset($discounts['discounts']));

$taxes = $api->getTaxes();
check('getTaxes',
      isset($taxes['taxes']) && count($taxes['taxes']) > 0,
      'taxes: ' . count($taxes['taxes'] ?? []));

$svcCharges = $api->getServiceCharges();
check('getServiceCharges', isset($svcCharges['serviceCharges']));

// ─── CHECKS ───────────────────────────────────────────────────────────────
section('CHECKS API');

$items = [
    ['itemId' => 'i-1', 'quantity' => 2, 'price' => 2500],
    ['itemId' => 'i-3', 'quantity' => 1, 'price' => 5500],
];

$calc = $api->calculateCheck($items, 5);
$expectedSub = 2 * 2500 + 1 * 5500; // 10500
$expectedTax = (int) round($expectedSub * 0.18); // 1890
$expectedTotal = $expectedSub + $expectedTax; // 12390
check('calculateCheck : subtotal correct',
      ($calc['subtotal'] ?? 0) === $expectedSub,
      "subtotal=" . ($calc['subtotal'] ?? '?'));
check('calculateCheck : tax = 18% du subtotal',
      ($calc['tax'] ?? 0) === $expectedTax,
      "tax=" . ($calc['tax'] ?? '?'));
check('calculateCheck : total = subtotal + tax',
      ($calc['total'] ?? 0) === $expectedTotal,
      "total=" . ($calc['total'] ?? '?'));

$uuid = bin2hex(random_bytes(8));
$check_ = $api->createCheck($items, 5, $uuid);
check('createCheck renvoie un checkRef',
      !empty($check_['checkRef']),
      "ref=" . ($check_['checkRef'] ?? '?'));
check('createCheck status = "submitted"',
      ($check_['status'] ?? '') === 'submitted');

$checkRef = $check_['checkRef'];

$retrieved = $api->getCheck($checkRef);
check('getCheck retrouve le check cree',
      ($retrieved['checkRef'] ?? '') === $checkRef);

$uuid2 = bin2hex(random_bytes(8));
$addedItems = [['itemId' => 'i-2', 'quantity' => 1, 'price' => 1500]];
$updated = $api->addItemsToCheck($checkRef, $addedItems, $uuid2);
check('addItemsToCheck ajoute des items',
      count($updated['items'] ?? []) > count($items),
      'items finaux: ' . count($updated['items'] ?? []));

$cancelled = $api->cancelCheck($checkRef, 'test cleanup');
check('cancelCheck annule le check', !empty($cancelled));

// ─── SYSTEME ──────────────────────────────────────────────────────────────
section('SYSTEME');

$status = $api->checkConnectionStatus();
check('checkConnectionStatus.ok = true',
      $status['ok'] === true,
      "status HTTP " . ($status['status'] ?? '?'));

$ping = $api->ping();
check('ping().ok = true',
      $ping['ok'] === true,
      "duration: " . ($ping['duration_ms'] ?? '?') . "ms, org: " . ($ping['org'] ?? '?'));

// ─── LOGS BDD ─────────────────────────────────────────────────────────────
section('LOGS BDD');

$logs = $pdo->query("SELECT operation, success, COUNT(*) AS nb FROM oracle_logs
                     WHERE restaurant_id=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                     GROUP BY operation, success ORDER BY operation")->fetchAll();
echo "  " . count($logs) . " operations distinctes loggees :\n";
foreach ($logs as $l) {
    $flag = $l['success'] ? '[OK]' : '[KO]';
    echo "    $flag " . $l['operation'] . " : " . $l['nb'] . "x\n";
}

// ─── ERREURS ──────────────────────────────────────────────────────────────
section('GESTION DES ERREURS');

try {
    $api->getCheck('CHK-INEXISTANT-ZZZ');
    check('getCheck sur ref inexistante leve exception', false);
} catch (\Throwable $e) {
    check('getCheck inexistant leve RuntimeException',
          str_contains($e->getMessage(), 'Check introuvable')
          || str_contains($e->getMessage(), 'HTTP 404'),
          $e->getMessage());
}

// Cleanup
$pdo->exec("UPDATE restaurants SET mode_integration='standalone',
    oracle_id_token=NULL, oracle_refresh_token=NULL, oracle_token_expires_at=NULL,
    oracle_api_username=NULL, oracle_api_password_enc=NULL,
    oracle_org_short_name=NULL, oracle_loc_ref=NULL, oracle_rvc_ref=NULL
    WHERE id=1");
$pdo->exec("DELETE FROM oracle_logs WHERE restaurant_id=1");

echo "\n" . str_repeat('=', 60) . "\n";
echo "  Cleanup OK. Suite de tests terminee.\n";
echo str_repeat('=', 60) . "\n";
