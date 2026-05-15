<?php
/**
 * app/models/Order.php
 * Modèle MVC — gestion des commandes
 * Rôle : accès BDD pour la table `commandes`
 */

class Order extends Model {
    protected string $table = 'commandes';

    /** Créer une commande */
    public function create(int $tableId, float $total, string $notes = ''): int {
        $this->execute(
            "INSERT INTO commandes (table_id, statut, total, notes, created_at, updated_at)
             VALUES (?, 'en_attente', ?, ?, NOW(), NOW())",
            [$tableId, $total, $notes]
        );
        return (int) $this->lastInsertId();
    }

    /** Mettre à jour le statut d'une commande */
    public function updateStatut(int $id, string $statut): bool {
        $allowed = ['en_attente', 'en_preparation', 'pret', 'servi', 'annule'];
        if (!in_array($statut, $allowed, true)) {
            return false;
        }

        $this->beginTransaction();
        try {
            $result = $this->execute(
                "UPDATE commandes SET statut = ?, updated_at = NOW() WHERE id = ?",
                [$statut, $id]
            );

            // Libérer la table si plus aucune commande active
            if ($result && in_array($statut, ['servi', 'annule'], true)) {
                $order = $this->queryOne(
                    "SELECT table_id FROM commandes WHERE id = ?", [$id]
                );
                if ($order) {
                    $still = $this->queryOne(
                        "SELECT COUNT(*) AS cnt FROM commandes
                         WHERE table_id = ? AND id != ?
                           AND statut NOT IN ('servi', 'annule')",
                        [$order['table_id'], $id]
                    );
                    if ($still && (int) $still['cnt'] === 0) {
                        $this->execute(
                            "UPDATE tables SET statut = 'libre' WHERE id = ?",
                            [$order['table_id']]
                        );
                    }
                }
            }

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            return false;
        }

        return $result;
    }

    /** Commande avec le numéro de table et les items */
    public function findWithDetails(int $id): array|false {
        $order = $this->queryOne(
            "SELECT c.*, t.numero as table_numero
             FROM commandes c
             JOIN tables t ON t.id = c.table_id
             WHERE c.id = ?",
            [$id]
        );

        if ($order) {
            $order['items'] = $this->query(
                "SELECT ci.*, m.nom, m.image
                 FROM commande_items ci
                 JOIN menu_items m ON m.id = ci.menu_item_id
                 WHERE ci.commande_id = ?",
                [$id]
            );
        }
        return $order;
    }

    /** Commandes actives pour la cuisine (en_attente + en_preparation) */
    public function getActiveForKitchen(): array {
        $orders = $this->query(
            "SELECT c.*, t.numero as table_numero
             FROM commandes c
             JOIN tables t ON t.id = c.table_id
             WHERE c.statut IN ('en_attente', 'en_preparation')
             ORDER BY c.created_at ASC"
        );

        foreach ($orders as &$order) {
            $order['items'] = $this->query(
                "SELECT ci.*, m.nom
                 FROM commande_items ci
                 JOIN menu_items m ON m.id = ci.menu_item_id
                 WHERE ci.commande_id = ?",
                [$order['id']]
            );
            // Temps écoulé via timestamps Unix → aucun problème de timezone
            $createdTs = strtotime($order['created_at']);
            $order['minutes_elapsed'] = max(0, (int) floor((time() - $createdTs) / 60));
            // Timestamp ms pour le timer JS (évite le parsing ambigu de chaînes date)
            $order['created_ts_ms']   = $createdTs * 1000;
        }
        return $orders;
    }

    /** Nouvelles commandes depuis un timestamp (polling) */
    public function getNewSince(string $since): array {
        return $this->query(
            "SELECT c.*, t.numero as table_numero
             FROM commandes c
             JOIN tables t ON t.id = c.table_id
             WHERE c.created_at > ?
               AND c.statut IN ('en_attente', 'en_preparation')
             ORDER BY c.created_at ASC",
            [$since]
        );
    }

    /** Historique des commandes pour l'admin */
    public function getHistory(array $filters = []): array {
        $where  = [];
        $params = [];

        if (!empty($filters['statut'])) {
            $where[]  = 'c.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['date'])) {
            $where[]  = 'DATE(c.created_at) = ?';
            $params[] = $filters['date'];
        }
        if (!empty($filters['table_id'])) {
            $where[]  = 'c.table_id = ?';
            $params[] = (int) $filters['table_id'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->query(
            "SELECT c.*, t.numero as table_numero
             FROM commandes c
             JOIN tables t ON t.id = c.table_id
             {$whereClause}
             ORDER BY c.created_at DESC
             LIMIT 100",
            $params
        );
    }

    /** Commandes filtrées par statut avec détail table */
    public function getByStatut(string $statut): array {
        return $this->query(
            "SELECT c.*, t.numero as table_numero
             FROM commandes c
             JOIN tables t ON t.id = c.table_id
             WHERE c.statut = ?
             ORDER BY c.created_at ASC",
            [$statut]
        );
    }

    /** Statistiques du jour pour le dashboard */
    public function getTodayStats(): array {
        $row = $this->queryOne(
            "SELECT
                COUNT(*) as nb_commandes,
                COALESCE(SUM(total), 0) as ca_total,
                COUNT(CASE WHEN statut = 'en_attente' THEN 1 END) as en_attente,
                COUNT(CASE WHEN statut = 'en_preparation' THEN 1 END) as en_preparation
             FROM commandes
             WHERE DATE(created_at) = CURDATE()"
        );
        return $row ?: ['nb_commandes' => 0, 'ca_total' => 0, 'en_attente' => 0, 'en_preparation' => 0];
    }
}
