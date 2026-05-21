<?php
/**
 * mock-oracle/index.php
 *
 * Mock du serveur Oracle Simphony STS Gen2 — pour tests locaux.
 *
 * Lancer avec :
 *   php -S localhost:8081 -t mock-oracle/
 *
 * Endpoints simules :
 *   AUTH :
 *     POST /oidc-provider/v1/oauth2/authorize    → renvoie auth_code
 *     POST /oidc-provider/v1/oauth2/signin       → renvoie auth_code (apres login)
 *     POST /oidc-provider/v1/oauth2/token        → echange code → id_token + refresh_token
 *
 *   MENU :
 *     GET  /api/v1/menus/summary
 *     GET  /api/v1/menus/{menuId}
 *     GET  /api/v1/menus/items/unavailable
 *     GET  /api/v1/discounts/collection
 *     GET  /api/v1/taxes
 *     GET  /api/v1/serviceCharges/collection
 *
 *   CHECKS :
 *     POST   /api/v1/checks/calculator
 *     POST   /api/v1/checks
 *     GET    /api/v1/checks/{ref}
 *     POST   /api/v1/checks/{ref}/round
 *     DELETE /api/v1/checks/{ref}
 *     HEAD   /api/v1/checks/connectionStatus
 *
 *   CONTROLE (pour tester les scenarios d echec) :
 *     POST /mock/reset                → reset etat
 *     POST /mock/expire-token         → expire tous les tokens
 *     POST /mock/fail/{n}             → fait echouer les n prochaines requetes (500)
 *     POST /mock/slow/{ms}            → ajoute un delai ms aux prochaines requetes
 *     GET  /mock/state                → voir l etat interne
 */

declare(strict_types=1);

// ─── State (persiste entre les requetes via fichier JSON) ───────────────────
$stateFile = __DIR__ . '/data/state.json';
if (!file_exists($stateFile)) {
    @mkdir(__DIR__ . '/data', 0755, true);
    file_put_contents($stateFile, json_encode([
        'tokens'    => [],
        'checks'    => [],
        'fail_next' => 0,
        'slow_ms'   => 0,
    ], JSON_PRETTY_PRINT));
}
$state = json_decode(file_get_contents($stateFile), true) ?: [];
$saveState = function() use (&$state, $stateFile) {
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
};

// ─── Helpers ────────────────────────────────────────────────────────────────
function send_json(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function send_error(string $msg, int $code = 400): void {
    send_json(['error' => $msg, 'code' => $code], $code);
}

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function bearer_token(): ?string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/', $h, $m)) return $m[1];
    return null;
}

function require_auth(array $state): string {
    $tok = bearer_token();
    if (!$tok || empty($state['tokens'][$tok])) {
        send_error('Token invalide ou manquant', 401);
    }
    if ($state['tokens'][$tok]['expires_at'] < time()) {
        send_error('Token expire', 401);
    }
    return $tok;
}

// ─── Simulation latence / erreurs (pour tests de resilience) ────────────────
if ($state['slow_ms'] > 0) {
    usleep($state['slow_ms'] * 1000);
}
if ($state['fail_next'] > 0) {
    $state['fail_next']--;
    $saveState();
    send_error('Erreur simulee (fail_next)', 500);
}

// ─── Routing ────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$path   = '/' . trim($path, '/');

// ─── CONTROL ENDPOINTS (helpers tests) ──────────────────────────────────────
if ($path === '/mock/state' && $method === 'GET') {
    send_json($state);
}
if ($path === '/mock/reset' && $method === 'POST') {
    $state = ['tokens'=>[], 'checks'=>[], 'fail_next'=>0, 'slow_ms'=>0];
    $saveState();
    send_json(['reset' => true]);
}
if ($path === '/mock/expire-token' && $method === 'POST') {
    foreach ($state['tokens'] as $k => $v) {
        $state['tokens'][$k]['expires_at'] = time() - 60;
    }
    $saveState();
    send_json(['expired' => count($state['tokens'])]);
}
if (preg_match('#^/mock/fail/(\d+)$#', $path, $m) && $method === 'POST') {
    $state['fail_next'] = (int) $m[1];
    $saveState();
    send_json(['fail_next' => $state['fail_next']]);
}
if (preg_match('#^/mock/slow/(\d+)$#', $path, $m) && $method === 'POST') {
    $state['slow_ms'] = (int) $m[1];
    $saveState();
    send_json(['slow_ms' => $state['slow_ms']]);
}

// ─── AUTH : OAuth2 PKCE ─────────────────────────────────────────────────────
// Etape 1 : authorize (en vrai Oracle redirect, ici on simule juste le code)
if ($path === '/oidc-provider/v1/oauth2/authorize' && in_array($method, ['GET','POST'], true)) {
    send_json([
        'authorization_code' => 'authcode_' . bin2hex(random_bytes(8)),
        'state'              => $_GET['state'] ?? null,
    ]);
}

// Etape 2 : signin (login user/pass → auth_code)
if ($path === '/oidc-provider/v1/oauth2/signin' && $method === 'POST') {
    $body = read_json_body();
    $u = $body['username'] ?? '';
    $p = $body['password'] ?? '';
    if (!$u || !$p) send_error('username/password requis', 400);
    // Le mock accepte tout sauf "wrong"
    if ($p === 'wrong') send_error('Invalid credentials', 401);
    send_json([
        'authorization_code' => 'authcode_' . bin2hex(random_bytes(8)),
    ]);
}

// Etape 3 : token (echange code → id_token + refresh_token)
if ($path === '/oidc-provider/v1/oauth2/token' && $method === 'POST') {
    $body = read_json_body() ?: $_POST;
    $grant = $body['grant_type'] ?? '';

    if ($grant === 'refresh_token') {
        $rt = $body['refresh_token'] ?? '';
        if (!$rt || !str_starts_with($rt, 'refresh_')) {
            send_error('refresh_token invalide', 401);
        }
    } elseif ($grant === 'authorization_code') {
        $code = $body['code'] ?? '';
        if (!$code || !str_starts_with($code, 'authcode_')) {
            send_error('code invalide', 401);
        }
        // En vrai Oracle, le code_verifier serait verifie ici. Mock accepte tout.
    } else {
        send_error('grant_type non supporte', 400);
    }

    $idToken      = 'idtoken_'      . bin2hex(random_bytes(16));
    $refreshToken = 'refresh_'      . bin2hex(random_bytes(16));
    $expiresIn    = 3600; // 1h
    $state['tokens'][$idToken] = [
        'expires_at'    => time() + $expiresIn,
        'refresh_token' => $refreshToken,
    ];
    $saveState();

    send_json([
        'id_token'       => $idToken,
        'access_token'   => $idToken, // certaines APIs Oracle utilisent un nom ou l autre
        'refresh_token'  => $refreshToken,
        'token_type'     => 'Bearer',
        'expires_in'     => $expiresIn,
    ]);
}

// ─── MENU ──────────────────────────────────────────────────────────────────
if ($path === '/api/v1/menus/summary' && $method === 'GET') {
    require_auth($state);
    send_json(['items' => [
        ['menuId' => 'menu-001', 'name' => 'Menu Principal', 'lastUpdated' => date('c')],
        ['menuId' => 'menu-bar', 'name' => 'Carte Bar',      'lastUpdated' => date('c')],
    ]]);
}

if (preg_match('#^/api/v1/menus/([^/]+)$#', $path, $m) && $method === 'GET') {
    require_auth($state);
    $menuId = $m[1];
    $fixture = __DIR__ . '/data/menu.json';
    if (file_exists($fixture)) {
        $data = json_decode(file_get_contents($fixture), true);
    } else {
        $data = [
            'menuId'     => $menuId,
            'name'       => 'Menu Principal',
            'categories' => [
                ['categoryId' => 'cat-1', 'name' => 'Entrées',  'items' => [
                    ['itemId'=>'i-1', 'name'=>'Salade César',   'price'=>2500, 'available'=>true],
                    ['itemId'=>'i-2', 'name'=>'Soupe du jour',  'price'=>1500, 'available'=>true],
                ]],
                ['categoryId' => 'cat-2', 'name' => 'Plats',    'items' => [
                    ['itemId'=>'i-3', 'name'=>'Poulet braisé',  'price'=>5500, 'available'=>true],
                    ['itemId'=>'i-4', 'name'=>'Tilapia frit',   'price'=>6000, 'available'=>false],
                ]],
            ],
        ];
    }
    send_json($data);
}

if ($path === '/api/v1/menus/items/unavailable' && $method === 'GET') {
    require_auth($state);
    send_json(['items' => [
        ['itemId' => 'i-4', 'reason' => 'rupture_stock'],
    ]]);
}

if ($path === '/api/v1/discounts/collection' && $method === 'GET') {
    require_auth($state);
    send_json(['discounts' => []]);
}

if ($path === '/api/v1/taxes' && $method === 'GET') {
    require_auth($state);
    send_json(['taxes' => [
        ['taxId' => 'tva-18', 'rate' => 0.18, 'name' => 'TVA 18%'],
    ]]);
}

if ($path === '/api/v1/serviceCharges/collection' && $method === 'GET') {
    require_auth($state);
    send_json(['serviceCharges' => []]);
}

// ─── CHECKS (commandes) ────────────────────────────────────────────────────
if ($path === '/api/v1/checks/calculator' && $method === 'POST') {
    require_auth($state);
    $body  = read_json_body();
    $items = $body['items'] ?? [];
    $sub   = 0;
    foreach ($items as $it) {
        $sub += ($it['price'] ?? 0) * ($it['quantity'] ?? 1);
    }
    $tax = (int) round($sub * 0.18);
    send_json([
        'subtotal' => $sub,
        'tax'      => $tax,
        'total'    => $sub + $tax,
    ]);
}

if ($path === '/api/v1/checks' && $method === 'POST') {
    require_auth($state);
    $body = read_json_body();
    $ref  = 'CHK-' . strtoupper(bin2hex(random_bytes(4)));
    $check = [
        'checkRef'      => $ref,
        'status'        => 'submitted',
        'tableNumber'   => $body['tableNumber'] ?? null,
        'items'         => $body['items']       ?? [],
        'idempotencyId' => $body['idempotencyId'] ?? null,
        'createdAt'     => date('c'),
        'updatedAt'     => date('c'),
    ];
    $state['checks'][$ref] = $check;
    $saveState();
    send_json($check, 201);
}

if (preg_match('#^/api/v1/checks/([^/]+)$#', $path, $m)) {
    require_auth($state);
    $ref = $m[1];
    if (!isset($state['checks'][$ref])) {
        if ($ref === 'connectionStatus') {
            // HEAD ou GET /checks/connectionStatus
            http_response_code(200);
            exit;
        }
        send_error('Check introuvable', 404);
    }
    if ($method === 'GET') {
        send_json($state['checks'][$ref]);
    }
    if ($method === 'DELETE') {
        $state['checks'][$ref]['status'] = 'cancelled';
        $state['checks'][$ref]['updatedAt'] = date('c');
        $saveState();
        send_json(['cancelled' => $ref]);
    }
}

if (preg_match('#^/api/v1/checks/([^/]+)/round$#', $path, $m) && $method === 'POST') {
    require_auth($state);
    $ref = $m[1];
    if (!isset($state['checks'][$ref])) send_error('Check introuvable', 404);
    $body = read_json_body();
    foreach ($body['items'] ?? [] as $it) {
        $state['checks'][$ref]['items'][] = $it;
    }
    $state['checks'][$ref]['updatedAt'] = date('c');
    $saveState();
    send_json($state['checks'][$ref]);
}

// ─── 404 par defaut ────────────────────────────────────────────────────────
send_error("Endpoint non implemente dans le mock: {$method} {$path}", 404);
