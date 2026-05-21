<?php
/**
 * app/services/OracleAuthService.php
 *
 * ROLE : Orchestrateur OAuth2 PKCE pour Oracle Simphony.
 *
 * FLUX COMPLET (RFC 7636 - PKCE) :
 *   1. generateCodeVerifier()  → chaine aleatoire 43-128 caracteres Base64URL
 *   2. generateCodeChallenge() → SHA-256(verifier), encode Base64URL
 *   3. requestAuthorizationCode() → GET /authorize (en vrai : redirect, mock : direct)
 *   4. signIn($user, $pass)    → POST /signin → renvoie authorization_code
 *   5. exchangeCodeForTokens() → POST /token → id_token + refresh_token
 *   6. refreshAccessToken()    → renouvelle sans refaire le PKCE complet
 *
 * METHODE PUBLIQUE PRINCIPALE :
 *   authenticate($restaurant_id)
 *   → Retourne un id_token valide pour l API Oracle.
 *   → Fait tout le travail : verifie cache, refresh si besoin, full flow sinon.
 *
 * STRATEGIE :
 *   - Token valide > 3 jours      : on l utilise tel quel
 *   - Token expire dans < 3 jours : on tente le refresh
 *   - Refresh echoue              : full PKCE flow
 */

require_once APP_PATH . '/models/Restaurant.php';
require_once APP_PATH . '/models/OracleLog.php';
require_once ROOT_PATH . '/core/OracleHttp.php';
require_once ROOT_PATH . '/core/Crypto.php';

class OracleAuthService {

    private int $restaurantId;
    private OracleHttp $http;
    private OracleLog $logger;
    private Restaurant $restoModel;

    /** Seuil de renouvellement proactif (3 jours en secondes) */
    private const REFRESH_THRESHOLD_SECS = 3 * 86400;

    public function __construct(int $restaurantId) {
        $this->restaurantId = $restaurantId;
        $this->http         = new OracleHttp($restaurantId);
        $this->logger       = new OracleLog();
        $this->restoModel   = new Restaurant();

        // Verifier que la config Oracle est chargee
        if (!defined('ORACLE_AUTH_URL') || !defined('ORACLE_CLIENT_ID')) {
            throw new \RuntimeException(
                'Configuration Oracle absente : copier config/oracle_config.example.php ' .
                'en config/oracle_config.php et la remplir.'
            );
        }
    }

    // ─── METHODE PUBLIQUE : authenticate ───────────────────────────────

    /**
     * Garantir un id_token valide pour ce restaurant.
     * @return string id_token utilisable dans Authorization: Bearer
     * @throws \RuntimeException si auth impossible
     */
    public function authenticate(): string {
        $creds = $this->restoModel->getOracleCreds($this->restaurantId);
        if (!$creds) {
            throw new \RuntimeException(
                "Restaurant {$this->restaurantId} : pas en mode Oracle ou credentials manquants"
            );
        }

        // 1. Token actuel encore valide assez longtemps ?
        if ($creds['oracle_id_token'] && $creds['oracle_token_expires_at']) {
            $expiresAt = strtotime($creds['oracle_token_expires_at']);
            $remaining = $expiresAt - time();
            if ($remaining > self::REFRESH_THRESHOLD_SECS) {
                $this->logger->logRequest(
                    $this->restaurantId, 'auth.cached', 'CACHE', '',
                    null, 200, null, 0, true,
                    "Token cache utilise, expire dans {$remaining}s"
                );
                return $creds['oracle_id_token'];
            }
        }

        // 2. Tenter le refresh si refresh_token disponible
        if ($creds['oracle_refresh_token']) {
            try {
                $newToken = $this->refreshAccessToken($creds['oracle_refresh_token']);
                return $newToken;
            } catch (\Throwable $e) {
                $this->logger->logRequest(
                    $this->restaurantId, 'auth.refresh.fail', 'POST', '',
                    null, 0, null, 0, false,
                    "Refresh echoue, fallback full flow : " . $e->getMessage()
                );
                // On enchaine sur le full flow
            }
        }

        // 3. Full flow OAuth2 PKCE
        return $this->fullPkceFlow($creds);
    }

    // ─── FULL FLOW PKCE ────────────────────────────────────────────────

    /**
     * Refait tout le flow PKCE quand le refresh echoue ou n existe pas.
     */
    private function fullPkceFlow(array $creds): string {
        // Step 1 : generer verifier + challenge
        $verifier  = $this->generateCodeVerifier();
        $challenge = $this->generateCodeChallenge($verifier);

        // Step 2 : sign-in pour obtenir authorization_code
        $authCode = $this->signIn(
            $creds['oracle_api_username'],
            $creds['oracle_api_password'],
            $creds['oracle_org_short_name'] ?? '',
            $challenge
        );

        // Step 3 : echanger code contre tokens
        return $this->exchangeCodeForTokens($authCode, $verifier);
    }

    // ─── HELPERS PKCE ──────────────────────────────────────────────────

    /**
     * Genere un code_verifier de 64 caracteres aleatoires Base64URL.
     * Spec : 43-128 caracteres (RFC 7636).
     */
    public function generateCodeVerifier(): string {
        $bytes = random_bytes(48); // 48 octets → 64 caracteres en Base64URL
        return $this->base64url($bytes);
    }

    /**
     * Genere un code_challenge = Base64URL(SHA256(verifier)).
     */
    public function generateCodeChallenge(string $verifier): string {
        return $this->base64url(hash('sha256', $verifier, true));
    }

    /** Base64URL safe (sans padding) */
    private function base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // ─── ETAPES OAUTH2 ─────────────────────────────────────────────────

    /**
     * POST /oidc-provider/v1/oauth2/signin
     * Authentifie l utilisateur et retourne un authorization_code temporaire.
     */
    public function signIn(string $username, string $password, string $orgname, string $codeChallenge): string {
        $url = rtrim(ORACLE_AUTH_URL, '/') . '/oidc-provider/v1/oauth2/signin';
        $resp = $this->http->post($url, [
            'username'              => $username,
            'password'              => $password,
            'organization'          => $orgname,
            'client_id'             => ORACLE_CLIENT_ID,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], [], 'auth.signin');

        if ($resp['status'] !== 200) {
            $msg = $resp['json']['error'] ?? "HTTP {$resp['status']}";
            throw new \RuntimeException("Sign-in Oracle echoue : {$msg}");
        }

        $code = $resp['json']['authorization_code'] ?? null;
        if (!$code) {
            throw new \RuntimeException('Sign-in Oracle : authorization_code manquant dans la reponse');
        }
        return $code;
    }

    /**
     * POST /oidc-provider/v1/oauth2/token
     * Echange un authorization_code contre id_token + refresh_token.
     */
    public function exchangeCodeForTokens(string $authCode, string $verifier): string {
        $url = rtrim(ORACLE_AUTH_URL, '/') . '/oidc-provider/v1/oauth2/token';
        $resp = $this->http->post($url, [
            'grant_type'    => 'authorization_code',
            'code'          => $authCode,
            'code_verifier' => $verifier,
            'client_id'     => ORACLE_CLIENT_ID,
        ], [], 'auth.token.exchange');

        if ($resp['status'] !== 200) {
            $msg = $resp['json']['error'] ?? "HTTP {$resp['status']}";
            throw new \RuntimeException("Echange code/token echoue : {$msg}");
        }

        $idToken      = $resp['json']['id_token']      ?? $resp['json']['access_token'] ?? null;
        $refreshToken = $resp['json']['refresh_token'] ?? null;
        $expiresIn    = (int) ($resp['json']['expires_in'] ?? 3600);

        if (!$idToken) {
            throw new \RuntimeException('Echange code/token : id_token manquant');
        }

        // Sauvegarder en BDD
        $this->restoModel->saveOracleTokens($this->restaurantId, $idToken, $refreshToken, $expiresIn);
        return $idToken;
    }

    /**
     * POST /oidc-provider/v1/oauth2/token avec grant_type=refresh_token
     * Renouvelle le id_token sans refaire le PKCE complet.
     */
    public function refreshAccessToken(string $refreshToken): string {
        $url = rtrim(ORACLE_AUTH_URL, '/') . '/oidc-provider/v1/oauth2/token';
        $resp = $this->http->post($url, [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id'     => ORACLE_CLIENT_ID,
        ], [], 'auth.token.refresh');

        if ($resp['status'] !== 200) {
            $msg = $resp['json']['error'] ?? "HTTP {$resp['status']}";
            throw new \RuntimeException("Refresh token echoue : {$msg}");
        }

        $idToken          = $resp['json']['id_token']      ?? $resp['json']['access_token'] ?? null;
        $newRefreshToken  = $resp['json']['refresh_token'] ?? $refreshToken; // Oracle peut rotater le refresh
        $expiresIn        = (int) ($resp['json']['expires_in'] ?? 3600);

        if (!$idToken) {
            throw new \RuntimeException('Refresh : id_token manquant dans la reponse');
        }

        $this->restoModel->saveOracleTokens($this->restaurantId, $idToken, $newRefreshToken, $expiresIn);
        return $idToken;
    }
}
