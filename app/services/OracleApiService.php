<?php
/**
 * app/services/OracleApiService.php
 *
 * ROLE : Facade pour TOUS les appels API Oracle Simphony.
 *        - Garantit qu un token valide est obtenu avant chaque appel (via OracleAuthService)
 *        - Construit les URLs avec orgShortName + locRef
 *        - Ajoute systematiquement les headers requis (Authorization, etc.)
 *        - Logge automatiquement chaque operation
 *
 * USAGE :
 *   $api = new OracleApiService($restaurant_id);
 *   $menus = $api->getMenuSummary();
 *   $check = $api->createCheck($items, 5, $uuid);
 */

require_once APP_PATH . '/services/OracleAuthService.php';
require_once ROOT_PATH . '/core/OracleHttp.php';
require_once APP_PATH . '/models/Restaurant.php';

class OracleApiService {

    private int $restaurantId;
    private array $creds;       // credentials du resto en BDD
    private OracleAuthService $auth;
    private OracleHttp $http;
    private Restaurant $restoModel;
    private string $baseUrl;

    public function __construct(int $restaurantId) {
        $this->restaurantId = $restaurantId;
        $this->restoModel   = new Restaurant();
        $this->auth         = new OracleAuthService($restaurantId);
        $this->http         = new OracleHttp($restaurantId);

        if (!defined('ORACLE_API_BASE_URL')) {
            throw new \RuntimeException('Configuration Oracle absente (ORACLE_API_BASE_URL)');
        }
        $this->baseUrl = rtrim(ORACLE_API_BASE_URL, '/');

        $creds = $this->restoModel->getOracleCreds($restaurantId);
        if (!$creds) {
            throw new \RuntimeException("Restaurant {$restaurantId} : credentials Oracle incomplets");
        }
        $this->creds = $creds;
    }

    // ─── MENUS ─────────────────────────────────────────────────────────

    /** Liste des menus disponibles (resumé) */
    public function getMenuSummary(): array {
        $resp = $this->http->get($this->apiUrl('/menus/summary'), $this->headers(), 'menu.summary');
        return $this->expectSuccess($resp, 'getMenuSummary');
    }

    /** Detail complet d un menu (categories + items + prix) */
    public function getMenu(string $menuId): array {
        $resp = $this->http->get($this->apiUrl('/menus/' . rawurlencode($menuId)), $this->headers(), 'menu.detail');
        return $this->expectSuccess($resp, 'getMenu');
    }

    /** Plats marques 86 (rupture de stock) */
    public function getUnavailableItems(): array {
        $resp = $this->http->get($this->apiUrl('/menus/items/unavailable'), $this->headers(), 'menu.unavailable');
        return $this->expectSuccess($resp, 'getUnavailableItems');
    }

    /** Remises configurees */
    public function getDiscounts(): array {
        $resp = $this->http->get($this->apiUrl('/discounts/collection'), $this->headers(), 'menu.discounts');
        return $this->expectSuccess($resp, 'getDiscounts');
    }

    /** Taxes (TVA, etc.) */
    public function getTaxes(): array {
        $resp = $this->http->get($this->apiUrl('/taxes'), $this->headers(), 'menu.taxes');
        return $this->expectSuccess($resp, 'getTaxes');
    }

    /** Service charges (frais de service) */
    public function getServiceCharges(): array {
        $resp = $this->http->get($this->apiUrl('/serviceCharges/collection'), $this->headers(), 'menu.service_charges');
        return $this->expectSuccess($resp, 'getServiceCharges');
    }

    // ─── CHECKS (ordres / addition) ────────────────────────────────────

    /**
     * Calculer un check sans le commit (preview du total + taxes + remises).
     * Utile cote client avant de confirmer la commande.
     */
    public function calculateCheck(array $items, ?int $tableNumber = null): array {
        $body = ['items' => $items];
        if ($tableNumber !== null) $body['tableNumber'] = $tableNumber;
        $resp = $this->http->post($this->apiUrl('/checks/calculator'), $body, $this->headers(), 'check.calculate');
        return $this->expectSuccess($resp, 'calculateCheck');
    }

    /**
     * Creer un check (envoyer la commande a la cuisine Oracle).
     * @param array  $items         Format Oracle : [{itemId, quantity, price, modifiers, ...}]
     * @param int    $tableNumber   Numero de la table
     * @param string $idempotencyId UUID pour eviter les doublons (rejouer = meme resultat)
     */
    public function createCheck(array $items, int $tableNumber, string $idempotencyId): array {
        $body = [
            'tableNumber'   => $tableNumber,
            'items'         => $items,
            'idempotencyId' => $idempotencyId,
            'employeeRef'   => $this->creds['oracle_api_username'] ?? null,
            'rvcRef'        => $this->creds['oracle_rvc_ref']      ?? null,
        ];

        $headers = $this->headers();
        $headers['Idempotency-Id'] = $idempotencyId;

        $resp = $this->http->post($this->apiUrl('/checks'), $body, $headers, 'check.create');
        $result = $this->expectSuccess($resp, 'createCheck');

        $this->restoModel->markOracleSynced($this->restaurantId);
        return $result;
    }

    /** Recuperer le statut d un check existant */
    public function getCheck(string $checkRef): array {
        $resp = $this->http->get($this->apiUrl('/checks/' . rawurlencode($checkRef)), $this->headers(), 'check.get');
        return $this->expectSuccess($resp, 'getCheck');
    }

    /** Ajouter des items a un check existant (relance / second tour) */
    public function addItemsToCheck(string $checkRef, array $items, string $idempotencyId): array {
        $headers = $this->headers();
        $headers['Idempotency-Id'] = $idempotencyId;
        $resp = $this->http->post(
            $this->apiUrl('/checks/' . rawurlencode($checkRef) . '/round'),
            ['items' => $items, 'idempotencyId' => $idempotencyId],
            $headers, 'check.add_items'
        );
        return $this->expectSuccess($resp, 'addItemsToCheck');
    }

    /** Annuler un check */
    public function cancelCheck(string $checkRef, string $reason = ''): array {
        $headers = $this->headers();
        if ($reason) $headers['X-Cancel-Reason'] = $reason;
        $resp = $this->http->delete($this->apiUrl('/checks/' . rawurlencode($checkRef)), $headers, 'check.cancel');
        return $this->expectSuccess($resp, 'cancelCheck');
    }

    /**
     * Statut de la connexion API (HEAD sans body, leger).
     * Utile pour les health checks rapides.
     */
    public function checkConnectionStatus(): array {
        try {
            $this->auth->authenticate();
            $resp = $this->http->head($this->apiUrl('/checks/connectionStatus'), $this->headers(), 'system.connection_status');
            return [
                'ok'          => $resp['status'] >= 200 && $resp['status'] < 400,
                'status'      => $resp['status'],
                'duration_ms' => $resp['duration_ms'],
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── UTILITAIRES ───────────────────────────────────────────────────

    /** Ping complet : auth + appel test (utilise par /superadmin/oracle/test) */
    public function ping(): array {
        try {
            $this->auth->authenticate();
            $resp = $this->http->get($this->apiUrl('/menus/summary'), $this->headers(), 'system.ping');
            return [
                'ok'           => $resp['status'] === 200,
                'status'       => $resp['status'],
                'duration_ms'  => $resp['duration_ms'],
                'org'          => $this->creds['oracle_org_short_name'],
                'loc'          => $this->creds['oracle_loc_ref'],
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── HELPERS PRIVES ────────────────────────────────────────────────

    /**
     * Construire une URL API.
     *
     * NB : la vraie API Simphony Gen2 utilise /api/v1/orgs/{org}/locations/{loc}{path}.
     *      Notre mock et la conf actuelle utilisent un schema simplifie.
     *      Pour basculer sur la vraie API : modifier cette methode.
     */
    private function apiUrl(string $path): string {
        return $this->baseUrl . '/api/v1' . $path;
    }

    /**
     * Headers communs a tous les appels :
     * - Authorization: Bearer {token}
     * - Content-Type: application/json (deja injecte par OracleHttp si body)
     * - X-RVC-Ref: revenue center (si configure)
     */
    private function headers(): array {
        $token = $this->auth->authenticate(); // recupere un token valide (cache, refresh, ou full)
        $h = [
            'Authorization' => 'Bearer ' . $token,
        ];
        if (!empty($this->creds['oracle_rvc_ref'])) {
            $h['X-RVC-Ref'] = $this->creds['oracle_rvc_ref'];
        }
        return $h;
    }

    /**
     * Verifier que la reponse est un 2xx et retourner le JSON.
     * Sinon lever une exception explicite.
     */
    private function expectSuccess(array $resp, string $operation): array {
        if ($resp['status'] >= 200 && $resp['status'] < 300) {
            return $resp['json'] ?? [];
        }
        $msg = $resp['json']['error']['message']
            ?? $resp['json']['error']
            ?? $resp['json']['message']
            ?? "HTTP {$resp['status']}";
        throw new \RuntimeException("Oracle {$operation} echec : {$msg}");
    }
}
