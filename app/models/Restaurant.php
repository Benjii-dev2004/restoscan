<?php
/**
 * app/models/Restaurant.php
 * Modele MVC - table maitre du multi-tenant
 * NE PAS scope par restaurant_id (c est LE restaurant)
 */

class Restaurant extends Model {
    protected string $table = 'restaurants';

    /** Trouver un restaurant par ID (sans scoping) */
    public function findByIdGlobal(int $id): array|false {
        return $this->queryOne("SELECT * FROM restaurants WHERE id = ?", [$id]);
    }

    /** Trouver un restaurant par slug */
    public function findBySlug(string $slug): array|false {
        return $this->queryOne("SELECT * FROM restaurants WHERE slug = ?", [$slug]);
    }

    /** Liste de tous les restaurants */
    public function listAll(): array {
        return $this->query("SELECT * FROM restaurants ORDER BY created_at DESC");
    }

    /** Liste avec stats agregees (nb tables, nb commandes du jour) */
    public function listAllWithStats(): array {
        return $this->query(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM tables WHERE restaurant_id = r.id) AS nb_tables,
                    (SELECT COUNT(*) FROM commandes
                     WHERE restaurant_id = r.id AND DATE(created_at) = CURDATE()) AS cmd_aujourdhui,
                    (SELECT COUNT(*) FROM users WHERE restaurant_id = r.id) AS nb_users
             FROM restaurants r
             ORDER BY r.created_at DESC"
        );
    }

    /** Creer un nouveau restaurant */
    public function create(array $data): int {
        $this->execute(
            "INSERT INTO restaurants
                (nom, slug, abonnement_debut, abonnement_fin, statut, formule,
                 gerant_email, gerant_telephone, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['nom'],
                $data['slug'],
                $data['abonnement_debut'] ?? null,
                $data['abonnement_fin']   ?? null,
                $data['statut']           ?? 'actif',
                $data['formule']          ?? 'starter',
                $data['gerant_email']     ?? null,
                $data['gerant_telephone'] ?? null,
            ]
        );
        return (int) $this->lastInsertId();
    }

    /** Mettre a jour le statut */
    public function setStatut(int $id, string $statut): bool {
        $allowed = ['actif', 'suspendu', 'expire'];
        if (!in_array($statut, $allowed, true)) return false;
        return $this->execute(
            "UPDATE restaurants SET statut = ? WHERE id = ?",
            [$statut, $id]
        );
    }

    /** Prolonger l abonnement de N mois (relance le statut + reset email tracking) */
    public function extendSubscription(int $id, int $months): bool {
        $ok = $this->execute(
            "UPDATE restaurants
             SET abonnement_fin = DATE_ADD(
                    GREATEST(IFNULL(abonnement_fin, NOW()), NOW()),
                    INTERVAL ? MONTH),
                 statut = 'actif',
                 email_30j_sent = NULL,
                 email_7j_sent = NULL,
                 email_expire_sent = NULL
             WHERE id = ?",
            [$months, $id]
        );
        return $ok;
    }

    /** Mise a jour generale */
    public function update(int $id, array $data): bool {
        return $this->execute(
            "UPDATE restaurants
             SET nom = ?, slug = ?, gerant_email = ?, gerant_telephone = ?,
                 formule = ?, abonnement_debut = ?, abonnement_fin = ?, statut = ?
             WHERE id = ?",
            [
                $data['nom'],
                $data['slug'],
                $data['gerant_email']     ?? null,
                $data['gerant_telephone'] ?? null,
                $data['formule']          ?? 'starter',
                $data['abonnement_debut'] ?? null,
                $data['abonnement_fin']   ?? null,
                $data['statut']           ?? 'actif',
                $id,
            ]
        );
    }

    /** Supprimer un restaurant (cascade sur toutes ses donnees) */
    public function deleteById(int $id): bool {
        return $this->execute("DELETE FROM restaurants WHERE id = ?", [$id]);
    }

    // ─── Oracle Simphony : credentials + tokens ─────────────────────────

    /**
     * Recupere les credentials Oracle d un resto (avec password DECHIFFRE).
     * Retourne null si le resto n est pas en mode oracle ou config incomplete.
     */
    public function getOracleCreds(int $id): ?array {
        $row = $this->queryOne(
            "SELECT id, mode_integration, oracle_org_short_name, oracle_loc_ref,
                    oracle_rvc_ref, oracle_api_username, oracle_api_password_enc,
                    oracle_id_token, oracle_refresh_token, oracle_token_expires_at,
                    oracle_password_expires_at, oracle_menu_id, oracle_last_sync
             FROM restaurants WHERE id = ?",
            [$id]
        );
        if (!$row || $row['mode_integration'] !== 'oracle') return null;
        if (!$row['oracle_api_username'] || !$row['oracle_api_password_enc']) return null;

        // Dechiffrer le mot de passe
        require_once ROOT_PATH . '/core/Crypto.php';
        try {
            $row['oracle_api_password'] = Crypto::decrypt($row['oracle_api_password_enc']);
        } catch (\Throwable $e) {
            error_log('[Restaurant] Echec dechiffrement password Oracle resto ' . $id . ': ' . $e->getMessage());
            return null;
        }
        return $row;
    }

    /**
     * Sauvegarder un mot de passe Oracle (chiffrement automatique).
     * Utilise par le super-admin lors de l onboarding d un client Oracle.
     */
    public function setOraclePassword(int $id, string $plainPassword): bool {
        require_once ROOT_PATH . '/core/Crypto.php';
        $encrypted = Crypto::encrypt($plainPassword);
        // Mot de passe Oracle expire tous les 60 jours
        $expiresAt = date('Y-m-d H:i:s', strtotime('+60 days'));
        return $this->execute(
            "UPDATE restaurants
             SET oracle_api_password_enc = ?,
                 oracle_password_expires_at = ?
             WHERE id = ?",
            [$encrypted, $expiresAt, $id]
        );
    }

    /**
     * Sauvegarder les tokens fraichement obtenus d Oracle.
     */
    public function saveOracleTokens(int $id, string $idToken, ?string $refreshToken, int $expiresInSecs): bool {
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresInSecs);
        if ($refreshToken) {
            return $this->execute(
                "UPDATE restaurants
                 SET oracle_id_token = ?, oracle_refresh_token = ?,
                     oracle_token_expires_at = ?
                 WHERE id = ?",
                [$idToken, $refreshToken, $expiresAt, $id]
            );
        }
        return $this->execute(
            "UPDATE restaurants
             SET oracle_id_token = ?, oracle_token_expires_at = ?
             WHERE id = ?",
            [$idToken, $expiresAt, $id]
        );
    }

    /** Marquer la derniere synchro reussie avec Oracle */
    public function markOracleSynced(int $id): bool {
        return $this->execute(
            "UPDATE restaurants SET oracle_last_sync = NOW() WHERE id = ?",
            [$id]
        );
    }

    /** Tous les restos en mode Oracle (utilise par le cron) */
    public function getAllOracleRestaurants(): array {
        return $this->query(
            "SELECT id, nom, slug, oracle_api_username, oracle_token_expires_at,
                    oracle_password_expires_at, gerant_email
             FROM restaurants
             WHERE mode_integration = 'oracle'
               AND statut = 'actif'"
        );
    }

    /** Stats globales pour le super admin */
    public function globalStats(): array {
        $row = $this->queryOne(
            "SELECT
                COUNT(*) AS nb_restos,
                COUNT(CASE WHEN statut = 'actif'    THEN 1 END) AS nb_actifs,
                COUNT(CASE WHEN statut = 'suspendu' THEN 1 END) AS nb_suspendus,
                COUNT(CASE WHEN statut = 'expire'   THEN 1 END) AS nb_expires,
                COUNT(CASE WHEN abonnement_fin IS NOT NULL
                            AND abonnement_fin < DATE_ADD(NOW(), INTERVAL 30 DAY)
                            AND abonnement_fin > NOW()
                          THEN 1 END) AS expirent_bientot
             FROM restaurants"
        );
        return $row ?: [];
    }

    /** Marquer un email d expiration comme envoye */
    public function markEmailSent(int $id, string $column): bool {
        $allowed = ['email_30j_sent', 'email_7j_sent', 'email_expire_sent'];
        if (!in_array($column, $allowed, true)) return false;
        return $this->execute(
            "UPDATE restaurants SET {$column} = NOW() WHERE id = ?",
            [$id]
        );
    }

    /** Reinitialiser le tracking emails (apres prolongation d abonnement) */
    public function resetEmailTracking(int $id): bool {
        return $this->execute(
            "UPDATE restaurants
             SET email_30j_sent = NULL, email_7j_sent = NULL, email_expire_sent = NULL
             WHERE id = ?",
            [$id]
        );
    }

    /** Generer un slug unique a partir d un nom */
    public function generateUniqueSlug(string $base): string {
        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($base, 'UTF-8'));
        $slug = trim($slug, '-');
        if ($slug === '') $slug = 'resto';
        $candidate = $slug;
        $i = 2;
        while ($this->findBySlug($candidate)) {
            $candidate = $slug . '-' . $i;
            $i++;
        }
        return $candidate;
    }
}
