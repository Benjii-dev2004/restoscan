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
