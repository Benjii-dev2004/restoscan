<?php
/**
 * app/models/OracleLog.php
 *
 * ROLE : Logger BDD pour TOUTES les interactions avec l API Oracle Simphony.
 *        Chaque appel (succes ou echec) est enregistre avec :
 *          - operation, endpoint, methode HTTP
 *          - payload envoye (apres sanitization des champs sensibles)
 *          - reponse (code + body tronque a 64 ko)
 *          - duree d execution
 *          - succes/erreur + message
 *
 * USAGE typique depuis OracleApiService :
 *
 *   $log = new OracleLog();
 *   $t0  = microtime(true);
 *   try {
 *       $resp = curl_exec(...);
 *       $log->logRequest($rid, 'menu.summary', 'GET', $url, [], 200, $resp,
 *                        (int)((microtime(true)-$t0)*1000), true);
 *   } catch (\Throwable $e) {
 *       $log->logRequest($rid, 'menu.summary', 'GET', $url, [], 0, null,
 *                        (int)((microtime(true)-$t0)*1000), false, $e->getMessage());
 *   }
 */

class OracleLog extends Model {
    protected string $table = 'oracle_logs';

    /** Cles a masquer dans request_body et response_body (sensible) */
    private const SENSITIVE_KEYS = [
        'password', 'passwd', 'pwd',
        'access_token', 'refresh_token', 'id_token', 'auth_code',
        'code_verifier', 'code_challenge',
        'client_secret', 'api_key', 'secret',
        'authorization',
    ];

    /** Taille max de body stockee (Oracle peut renvoyer des menus tres lourds) */
    private const MAX_BODY_SIZE = 65000;

    /**
     * Enregistrer un appel Oracle.
     */
    public function logRequest(
        int $restaurantId,
        string $operation,
        string $method,
        string $endpoint,
        $requestBody    = null,
        ?int $responseCode = null,
        ?string $responseBody = null,
        ?int $durationMs   = null,
        bool $success      = false,
        ?string $errorMessage = null
    ): void {
        $sanitizedReq = $this->sanitize($requestBody);
        $reqJson      = is_string($sanitizedReq) ? $sanitizedReq : json_encode($sanitizedReq, JSON_UNESCAPED_UNICODE);

        if ($responseBody !== null && strlen($responseBody) > self::MAX_BODY_SIZE) {
            $responseBody = substr($responseBody, 0, self::MAX_BODY_SIZE) . "\n... [tronque]";
        }

        try {
            $this->execute(
                "INSERT INTO oracle_logs
                    (restaurant_id, operation, endpoint, method, request_body,
                     response_code, response_body, duration_ms, success, error_message, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $restaurantId,
                    $operation,
                    $endpoint,
                    strtoupper($method),
                    $reqJson,
                    $responseCode,
                    $responseBody,
                    $durationMs,
                    $success ? 1 : 0,
                    $errorMessage,
                ]
            );
        } catch (\Throwable $e) {
            // Ne JAMAIS faire planter l app a cause d un echec de log
            error_log('[OracleLog] Echec insertion log : ' . $e->getMessage());
        }
    }

    /** Recursivement masquer les cles sensibles dans un payload */
    private function sanitize($data) {
        if (is_string($data)) {
            // Si c est du JSON, on parse et resanitize
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                return $this->sanitize($decoded);
            }
            return $data;
        }
        if (!is_array($data)) return $data;

        $out = [];
        foreach ($data as $key => $val) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                if (is_string($val)) {
                    $out[$key] = strlen($val) > 8 ? '***' . substr($val, -4) : '***';
                } else {
                    $out[$key] = '***';
                }
            } elseif (is_array($val)) {
                $out[$key] = $this->sanitize($val);
            } else {
                $out[$key] = $val;
            }
        }
        return $out;
    }

    private function isSensitiveKey(string $key): bool {
        $lower = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $k) {
            if (str_contains($lower, $k)) return true;
        }
        return false;
    }

    // ─── Methodes de lecture (pour dashboard admin) ──────────────────────

    /** Logs recents pour un resto */
    public function getRecentForRestaurant(int $restaurantId, int $limit = 50): array {
        return $this->query(
            "SELECT * FROM oracle_logs
             WHERE restaurant_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$restaurantId, $limit]
        );
    }

    /** Stats agregees sur 24h */
    public function statsLast24h(?int $restaurantId = null): array {
        $where  = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $params = [];
        if ($restaurantId) {
            $where .= " AND restaurant_id = ?";
            $params[] = $restaurantId;
        }
        $row = $this->queryOne(
            "SELECT
                COUNT(*)                                   AS total,
                SUM(CASE WHEN success=1 THEN 1 ELSE 0 END) AS ok,
                SUM(CASE WHEN success=0 THEN 1 ELSE 0 END) AS ko,
                ROUND(AVG(duration_ms))                    AS avg_ms
             FROM oracle_logs {$where}",
            $params
        );
        return $row ?: ['total'=>0,'ok'=>0,'ko'=>0,'avg_ms'=>0];
    }

    /** Top erreurs des 24 dernieres heures */
    public function topErrorsLast24h(int $limit = 10): array {
        return $this->query(
            "SELECT operation, response_code, COUNT(*) AS nb, MAX(error_message) AS sample
             FROM oracle_logs
             WHERE success = 0
               AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY operation, response_code
             ORDER BY nb DESC
             LIMIT ?",
            [$limit]
        );
    }

    /** Purge des vieux logs (> 30 jours), appele par cron */
    public function purgeOld(int $days = 30): int {
        $stmt = $this->db->prepare(
            "DELETE FROM oracle_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
