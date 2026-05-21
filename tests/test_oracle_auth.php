<?php
/**
 * Test d integration OracleAuthService contre le mock Oracle.
 *
 * Lancer :
 *   1. Demarrer le mock : php -S localhost:8081 -t mock-oracle/
 *   2. Dans une autre fenetre : php tests/test_oracle_auth.php
 */
define('ENV', 'development');
define('APP_PATH', __DIR__ . '/../app');
define('ROOT_PATH', __DIR__ . '/..');

// Constantes Oracle (normalement dans oracle_config.php)
define('ORACLE_API_BASE_URL', 'http://localhost:8081');
define('ORACLE_AUTH_URL',     'http://localhost:8081');
define('ORACLE_CLIENT_ID',    'restoscan-test');
define('ORACLE_TIMEOUT',         30);
define('ORACLE_RETRY_ATTEMPTS',  2);
define('ORACLE_ENCRYPTION_KEY', str_repeat('a', 64));

// Constantes config app (normalement dans config.php)
define('DB_HOST', 'localhost');
define('DB_NAME', 'restoscan');
define('DB_USER', 'root');
define('DB_PASS', '');
define('CSRF_TOKEN_NAME', '_csrf_token');

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/core/Crypto.php';
require_once ROOT_PATH . '/core/OracleHttp.php';
require_once APP_PATH . '/models/OracleLog.php';
require_once APP_PATH . '/models/Restaurant.php';
require_once APP_PATH . '/services/OracleAuthService.php';

$pdo = Database::getInstance();

// Setup resto 1 en mode oracle avec bon password
$pwd = Crypto::encrypt('test_password');
$stmt = $pdo->prepare("UPDATE restaurants SET
    mode_integration='oracle',
    oracle_org_short_name='TEST_ORG',
    oracle_api_username='test_user',
    oracle_api_password_enc=?,
    oracle_id_token=NULL,
    oracle_refresh_token=NULL,
    oracle_token_expires_at=NULL
    WHERE id=1");
$stmt->execute([$pwd]);

echo "=== TEST 1 : Full PKCE flow (premier login) ===\n";
$auth = new OracleAuthService(1);
$token = $auth->authenticate();
echo "Token: " . substr($token, 0, 25) . "...\n";

echo "\n=== TEST 2 : Cache hit ===\n";
$auth2 = new OracleAuthService(1);
$token2 = $auth2->authenticate();
echo "Token (cache): " . substr($token2, 0, 25) . "...\n";
echo "Cache fonctionne: " . ($token === $token2 ? "OUI" : "NON") . "\n";

echo "\n=== TEST 3 : Refresh (token expire bientot) ===\n";
$pdo->exec("UPDATE restaurants SET oracle_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=1");
$auth3 = new OracleAuthService(1);
$token3 = $auth3->authenticate();
echo "Token (refresh): " . substr($token3, 0, 25) . "...\n";
echo "Token different: " . ($token3 !== $token2 ? "OUI (refresh OK)" : "NON") . "\n";

echo "\n=== TEST 4 : Bad password = exception ===\n";
$wrongPwd = Crypto::encrypt('wrong');
$stmt = $pdo->prepare("UPDATE restaurants SET oracle_api_password_enc=?, oracle_id_token=NULL, oracle_refresh_token=NULL WHERE id=1");
$stmt->execute([$wrongPwd]);
try {
    $auth4 = new OracleAuthService(1);
    $auth4->authenticate();
    echo "ECHEC : exception attendue\n";
} catch (\Throwable $e) {
    echo "Exception OK : " . $e->getMessage() . "\n";
}

echo "\n=== TEST 5 : Logs en BDD ===\n";
$logs = $pdo->query("SELECT operation, success, COUNT(*) AS nb FROM oracle_logs WHERE restaurant_id=1 GROUP BY operation, success ORDER BY operation")->fetchAll();
foreach ($logs as $log) {
    $flag = $log['success'] ? '[OK]' : '[KO]';
    echo "  $flag " . $log['operation'] . " : " . $log['nb'] . "x\n";
}

// Cleanup
$pdo->exec("UPDATE restaurants SET mode_integration='standalone', oracle_id_token=NULL, oracle_refresh_token=NULL, oracle_token_expires_at=NULL, oracle_api_username=NULL, oracle_api_password_enc=NULL WHERE id=1");
$pdo->exec("DELETE FROM oracle_logs WHERE restaurant_id=1");
echo "\nCleanup OK.\n";
