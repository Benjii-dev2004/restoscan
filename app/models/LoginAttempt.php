<?php
/**
 * app/models/LoginAttempt.php
 *
 * Tracker brute-force PERSISTANT (en BDD, pas en session).
 * Resiste a la suppression du cookie de session par l attaquant.
 */

class LoginAttempt extends Model {
    protected string $table = 'login_attempts';

    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECS = 900; // 15 min

    /**
     * Verifier si l IP est actuellement bloquee.
     * @return int 0 si pas bloque, sinon secondes restantes avant deblocage
     */
    public function getLockoutRemaining(string $ip, string $scope = 'user'): int {
        $row = $this->queryOne(
            "SELECT locked_until FROM login_attempts WHERE ip = ? AND scope = ?",
            [$ip, $scope]
        );
        if (!$row || !$row['locked_until']) return 0;
        $remaining = strtotime($row['locked_until']) - time();
        return max(0, $remaining);
    }

    /** Enregistrer une tentative echec et bloquer si seuil atteint */
    public function registerFailure(string $ip, string $scope = 'user'): int {
        // Recuperer l etat actuel
        $row = $this->queryOne(
            "SELECT attempts FROM login_attempts WHERE ip = ? AND scope = ?",
            [$ip, $scope]
        );
        $newCount = ($row ? (int) $row['attempts'] : 0) + 1;
        $locked   = null;
        if ($newCount >= self::MAX_ATTEMPTS) {
            $locked = date('Y-m-d H:i:s', time() + self::LOCKOUT_SECS);
        }

        $this->execute(
            "INSERT INTO login_attempts (ip, scope, attempts, locked_until, last_attempt)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                attempts = ?,
                locked_until = ?,
                last_attempt = NOW()",
            [$ip, $scope, $newCount, $locked, $newCount, $locked]
        );

        return $newCount;
    }

    /** Reinitialiser apres login reussi */
    public function resetForIp(string $ip, string $scope = 'user'): void {
        $this->execute(
            "DELETE FROM login_attempts WHERE ip = ? AND scope = ?",
            [$ip, $scope]
        );
    }

    /** Nettoyer les vieux enregistrements (anciens > 30 jours) */
    public function purgeOld(): int {
        $stmt = $this->db->prepare(
            "DELETE FROM login_attempts WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function maxAttempts(): int     { return self::MAX_ATTEMPTS; }
    public function lockoutSeconds(): int  { return self::LOCKOUT_SECS; }
}
