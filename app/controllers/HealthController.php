<?php
/**
 * app/controllers/HealthController.php
 *
 * Endpoint public /healthz pour le monitoring externe (UptimeRobot).
 * Retourne 200 OK si la BDD repond, 500 sinon.
 * Volontairement minimaliste : pas d info exposee.
 */

class HealthController extends Controller {

    public function check(): void {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->query("SELECT 1");
            $result = $stmt->fetchColumn();
            if ($result !== 1 && $result !== '1') {
                throw new \RuntimeException('DB ping failed');
            }
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            http_response_code(200);
            echo 'OK';
        } catch (\Throwable $e) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(503);
            echo 'DOWN';
            error_log('[RESTOSCAN] Healthcheck failed: ' . $e->getMessage());
        }
        exit;
    }
}
