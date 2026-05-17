<?php
/**
 * app/models/Table.php
 * Modele MVC - tables du restaurant (scope par restaurant_id)
 */

class Table extends Model {
    protected string $table = 'tables';

    /**
     * Trouver une table par son token QR (recherche GLOBALE).
     * Le qr_token est unique mondialement et identifie a la fois la table
     * ET le restaurant. C est le point d entree public.
     */
    public function findByToken(string $token): array|false {
        return $this->queryOne(
            "SELECT t.*, r.statut AS restaurant_statut, r.abonnement_fin
             FROM tables t
             JOIN restaurants r ON r.id = t.restaurant_id
             WHERE t.qr_token = ?",
            [$token]
        );
    }

    public function create(int $numero, int $capacite): array|false {
        $rid   = $this->requireRestaurant();
        $token = bin2hex(random_bytes(32));
        $this->execute(
            "INSERT INTO tables (restaurant_id, numero, qr_token, capacite, statut, created_at)
             VALUES (?, ?, ?, ?, 'libre', NOW())",
            [$rid, $numero, $token, $capacite]
        );
        $id = (int) $this->lastInsertId();
        return $this->findById($id);
    }

    public function updateStatut(int $id, string $statut): bool {
        $rid = $this->requireRestaurant();
        $allowed = ['libre', 'occupee', 'reservee'];
        if (!in_array($statut, $allowed, true)) return false;
        return $this->execute(
            "UPDATE tables SET statut = ?
             WHERE id = ? AND restaurant_id = ?",
            [$statut, $id, $rid]
        );
    }

    /**
     * Variante interne sans require (utilisee depuis OrderController
     * qui ne dispose pas forcement d une session admin).
     */
    public function updateStatutByRid(int $id, int $rid, string $statut): bool {
        $allowed = ['libre', 'occupee', 'reservee'];
        if (!in_array($statut, $allowed, true)) return false;
        return $this->execute(
            "UPDATE tables SET statut = ?
             WHERE id = ? AND restaurant_id = ?",
            [$statut, $id, $rid]
        );
    }

    public function getAllWithStatus(): array {
        $rid = $this->requireRestaurant();
        return $this->query(
            "SELECT t.*,
                    COUNT(c.id) as commandes_actives
             FROM tables t
             LEFT JOIN commandes c ON c.table_id = t.id
                 AND c.statut NOT IN ('servi', 'annule')
             WHERE t.restaurant_id = ?
             GROUP BY t.id
             ORDER BY t.numero ASC",
            [$rid]
        );
    }

    public function hasActiveOrders(int $id): bool {
        $rid = $this->requireRestaurant();
        $row = $this->queryOne(
            "SELECT COUNT(*) AS cnt FROM commandes
             WHERE table_id = ? AND restaurant_id = ?
               AND statut NOT IN ('servi', 'annule')",
            [$id, $rid]
        );
        return $row && (int) $row['cnt'] > 0;
    }

    /** Resync statut des tables en fonction des commandes actives */
    public function syncStatuts(): void {
        $rid = $this->requireRestaurant();
        $this->execute(
            "UPDATE tables t
             SET t.statut = 'libre'
             WHERE t.restaurant_id = ? AND t.statut = 'occupee'
               AND NOT EXISTS (
                   SELECT 1 FROM commandes c
                   WHERE c.table_id = t.id
                     AND c.statut NOT IN ('servi', 'annule')
               )",
            [$rid]
        );
        $this->execute(
            "UPDATE tables t
             SET t.statut = 'occupee'
             WHERE t.restaurant_id = ? AND t.statut = 'libre'
               AND EXISTS (
                   SELECT 1 FROM commandes c
                   WHERE c.table_id = t.id
                     AND c.statut NOT IN ('servi', 'annule')
               )",
            [$rid]
        );
    }
}
