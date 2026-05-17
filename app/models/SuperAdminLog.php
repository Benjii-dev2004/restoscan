<?php
/**
 * app/models/SuperAdminLog.php
 * Journal des actions super-admin (audit trail)
 */

class SuperAdminLog extends Model {
    protected string $table = 'super_admin_logs';

    /**
     * Enregistrer une action.
     * @param int    $saId           ID du super-admin
     * @param string $action         create | extend | suspend | activate | delete | impersonate
     * @param ?int   $targetRestoId  ID du restaurant concerne (null si N/A)
     * @param string $details        Description libre
     */
    public function log(int $saId, string $action, ?int $targetRestoId, string $details = ''): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->execute(
            "INSERT INTO super_admin_logs
                (super_admin_id, action, target_restaurant_id, details, ip, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$saId, $action, $targetRestoId, $details, $ip]
        );
    }

    /** Liste paginated avec join sur super_admin + restaurant */
    public function getRecent(int $limit = 100): array {
        return $this->query(
            "SELECT l.*, sa.nom AS sa_nom, sa.email AS sa_email, r.nom AS resto_nom
             FROM super_admin_logs l
             LEFT JOIN super_admins sa ON sa.id = l.super_admin_id
             LEFT JOIN restaurants r   ON r.id  = l.target_restaurant_id
             ORDER BY l.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }
}
