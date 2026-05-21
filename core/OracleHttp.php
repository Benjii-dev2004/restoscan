<?php
/**
 * core/OracleHttp.php
 *
 * ROLE : Client HTTP pour TOUS les appels Oracle Simphony.
 *        Encapsule curl avec :
 *          - timeout configurable (defaut : ORACLE_TIMEOUT)
 *          - retry automatique avec back-off exponentiel
 *          - logging automatique dans oracle_logs
 *          - gestion uniforme des erreurs
 *
 * USAGE :
 *   $http = new OracleHttp($restaurant_id);
 *   $resp = $http->get('/api/v1/menus/menu-001', ['Authorization' => 'Bearer xxx']);
 *   // $resp = ['status' => 200, 'body' => '...', 'json' => [...], 'duration_ms' => 234]
 */

require_once APP_PATH . '/models/OracleLog.php';

class OracleHttp {

    private int $restaurantId;
    private int $timeout;
    private int $maxRetries;
    private OracleLog $logger;

    public function __construct(int $restaurantId) {
        $this->restaurantId = $restaurantId;
        $this->timeout      = defined('ORACLE_TIMEOUT')         ? ORACLE_TIMEOUT         : 30;
        $this->maxRetries   = defined('ORACLE_RETRY_ATTEMPTS')  ? ORACLE_RETRY_ATTEMPTS  : 3;
        $this->logger       = new OracleLog();
    }

    public function get(string $url, array $headers = [], string $operation = 'http.get'): array {
        return $this->request('GET', $url, null, $headers, $operation);
    }

    public function post(string $url, $body = null, array $headers = [], string $operation = 'http.post'): array {
        return $this->request('POST', $url, $body, $headers, $operation);
    }

    public function delete(string $url, array $headers = [], string $operation = 'http.delete'): array {
        return $this->request('DELETE', $url, null, $headers, $operation);
    }

    public function head(string $url, array $headers = [], string $operation = 'http.head'): array {
        return $this->request('HEAD', $url, null, $headers, $operation);
    }

    /**
     * Requete HTTP generique avec retry et logging.
     *
     * @return array ['status' => int, 'body' => string|null, 'json' => array|null, 'duration_ms' => int]
     */
    public function request(string $method, string $url, $body = null, array $headers = [], string $operation = 'http.request'): array {
        $attempt    = 0;
        $lastError  = null;
        $start      = microtime(true);

        // Normaliser les headers en format curl
        $curlHeaders = [];
        foreach ($headers as $key => $val) {
            $curlHeaders[] = is_int($key) ? $val : "{$key}: {$val}";
        }
        $curlHeaders[] = 'Accept: application/json';
        // Body JSON par defaut si c est un array
        if (is_array($body)) {
            $curlHeaders[] = 'Content-Type: application/json';
            $body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        while ($attempt < $this->maxRetries) {
            $attempt++;
            $attemptStart = microtime(true);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER     => $curlHeaders,
                CURLOPT_NOBODY         => $method === 'HEAD',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            if ($body !== null && $method !== 'GET' && $method !== 'HEAD') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $responseBody = curl_exec($ch);
            $statusCode   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError    = curl_error($ch);
            curl_close($ch);

            $durationMs = (int) round((microtime(true) - $attemptStart) * 1000);

            // Echec total reseau (timeout, DNS, etc.)
            if ($statusCode === 0) {
                $lastError = $curlError ?: 'Erreur reseau inconnue';
                $this->logger->logRequest(
                    $this->restaurantId, $operation . '.retry', $method, $url,
                    $body, 0, null, $durationMs, false,
                    "Tentative {$attempt}/{$this->maxRetries} : {$lastError}"
                );
                // Retry avec back-off : 1s, 2s, 4s
                if ($attempt < $this->maxRetries) {
                    sleep(pow(2, $attempt - 1));
                    continue;
                }
                break;
            }

            // Erreur serveur 5xx : on retry aussi
            if ($statusCode >= 500 && $attempt < $this->maxRetries) {
                $this->logger->logRequest(
                    $this->restaurantId, $operation . '.retry', $method, $url,
                    $body, $statusCode, $responseBody, $durationMs, false,
                    "Tentative {$attempt}/{$this->maxRetries} : HTTP {$statusCode}"
                );
                sleep(pow(2, $attempt - 1));
                continue;
            }

            // Succes (2xx) ou erreur client (4xx) : on retourne tel quel
            $totalDuration = (int) round((microtime(true) - $start) * 1000);
            $json = null;
            if ($responseBody && in_array(substr(trim($responseBody), 0, 1), ['{','['], true)) {
                $json = json_decode($responseBody, true);
            }

            $this->logger->logRequest(
                $this->restaurantId, $operation, $method, $url,
                $body, $statusCode, $responseBody, $totalDuration,
                $statusCode >= 200 && $statusCode < 300,
                $statusCode >= 400 ? "HTTP {$statusCode}" : null
            );

            return [
                'status'      => $statusCode,
                'body'        => $responseBody,
                'json'        => $json,
                'duration_ms' => $totalDuration,
            ];
        }

        // Echec apres tous les retries
        $totalDuration = (int) round((microtime(true) - $start) * 1000);
        $this->logger->logRequest(
            $this->restaurantId, $operation, $method, $url,
            $body, 0, null, $totalDuration, false,
            "Echec apres {$this->maxRetries} tentatives : {$lastError}"
        );
        throw new \RuntimeException("Oracle HTTP {$method} {$url} a echoue : {$lastError}");
    }
}
