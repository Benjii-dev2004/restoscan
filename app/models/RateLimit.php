<?php
/**
 * app/models/RateLimit.php
 *
 * Rate limiting generique en fenetre fixe.
 * Utilise pour proteger les endpoints publics (commandes, etc.) du DoS.
 */

class RateLimit extends Model {
    protected string $table = 'rate_limits';

    /**
     * Enregistrer une "hit" pour une cle donnee.
     *
     * @param string $key          Identifiant unique (ex: "order:ip:1.2.3.4" ou "order:token:abc...")
     * @param int    $maxHits      Nombre max de hits dans la fenetre
     * @param int    $windowSecs   Duree de la fenetre en secondes
     * @return bool true si OK, false si quota depasse
     */
    public function hit(string $key, int $maxHits, int $windowSecs): bool {
        $row = $this->queryOne(
            "SELECT hits, window_start FROM rate_limits WHERE rl_key = ?",
            [$key]
        );

        $now = time();

        // Nouvelle fenetre si rien d existant ou fenetre expiree
        if (!$row || (strtotime($row['window_start']) + $windowSecs) < $now) {
            $this->execute(
                "INSERT INTO rate_limits (rl_key, hits, window_start)
                 VALUES (?, 1, NOW())
                 ON DUPLICATE KEY UPDATE hits = 1, window_start = NOW()",
                [$key]
            );
            return true;
        }

        // Quota depasse
        if ((int) $row['hits'] >= $maxHits) {
            return false;
        }

        // Incrementer
        $this->execute(
            "UPDATE rate_limits SET hits = hits + 1 WHERE rl_key = ?",
            [$key]
        );
        return true;
    }

    /** Reset une cle (utile pour les tests ou un unblock manuel) */
    public function reset(string $key): void {
        $this->execute("DELETE FROM rate_limits WHERE rl_key = ?", [$key]);
    }

    /** Nettoyer les fenetres expirees (plus vieilles que 1h) */
    public function purgeOld(): int {
        $stmt = $this->db->prepare(
            "DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
