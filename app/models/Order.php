<?php
/**
 * app/models/Order.php
 * Modele MVC - commandes (scope par restaurant_id)
 */

class Order extends Model {
    protected string $table = 'commandes';

    public function create(int $tableId, float $total, string $notes = ''): int {
        $rid = $this->requireRestaurant();
        $this->execute(
            "INSERT INTO commandes
                (restaurant_id, table_id, statut, total, notes, created_at, updated_at)
             VALUES (?, ?, 'en_attente', ?, ?, NOW(), NOW())",
            [$rid, $tableId, $total, $notes]
        );
        return (int) $this->lastInsertId();
    }

    public function updateStatut(int $id, string $statut): bool {
        $rid = $this->requireRestaurant();
        $allowed = ['en_attente', 'en_preparation', 'pret', 'servi', 'annule'];
        if (!in_array($statut, $allowed, true)) return false;

        $this->beginTransaction();
        try {
            $result = $this->execute(
                "UPDATE commandes SET statut = ?, updated_at = NOW()
                 WHERE id = ? AND restaurant_id = ?",
                [$statut, $id, $rid]
            );

            // Liberer la table si plus aucune commande active
            if ($result && in_array($statut, ['servi', 'annule'], true)) {
                $order = $this->queryOne(
                    "SELECT table_id FROM commandes
                     WHERE id = ? AND restaurant_id = ?",
                    [$id, $rid]
                );
                if ($order) {
                    $still = $this->queryOne(
                        "SELECT COUNT(*) AS cnt FROM commandes
                         WHERE table_id = ? AND restaurant_id = ? AND id != ?
                           AND statut NOT IN ('servi', 'annule')",
                        [$order['table_id'], $rid, $id]
                    );
                    if ($still && (int) $still['cnt'] === 0) {
                        $this->execute(
                            "UPDATE tables SET statut = 'libre'
                             WHERE id = ? AND restaurant_id = ?",
                            [$order['table_id'], $rid]
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

    public function findWithDetails(int $id): array|false {
        $rid = $this->requireRestaurant();
        $order = $this->queryOne(
            "SELECT c.*, t.numero as table_numero
             FROM commandes c
             JOIN tables t ON t.id = c.table_id
             WHERE c.id = ? AND c.restaurant_id = ?",
            [$id, $rid]
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

    public function getActiveForKitchen(): array {
        $rid = $this->requireRestaurant();
        $orders = $this->query(
            "SELECT c.*, t.numero as table_numero
             FROM commandes c
             JOIN tables t ON t.id = c.table_id
             WHERE c.restaurant_id = ?
               AND c.statut IN ('en_attente', 'en_preparation')
             ORDER BY c.created_at ASC",
            [$rid]
        );

        foreach ($orders as &$order) {
            $order['items'] = $this->query(
                "SELECT ci.*, m.nom
                 FROM commande_items ci
                 JOIN menu_items m ON m.id = ci.menu_item_id
                 WHERE ci.commande_id = ?",
                [$order['id']]
            );
            $createdTs = strtotime($order['created_at']);
            $order['minutes_elapsed'] = max(0, (int) floor((time() - $createdTs) / 60));
            $order['created_ts_ms']   = $createdTs * 1000;
        }
        return $orders;
    }

    public function getNewSince(string $since): array {
        $rid = $this->requireRestaurant();
        return $this->query(
            "SELECT c.*, t.numero as table_numero
             FROM commandes c
             JOIN tables t ON t.id = c.table_id
             WHERE c.restaurant_id = ?
               AND c.created_at > ?
               AND c.statut IN ('en_attente', 'en_preparation')
             ORDER BY c.created_at ASC",
            [$rid, $since]
        );
    }

    public function getHistory(array $filters = []): array {
        $rid = $this->requireRestaurant();
        $where  = ['c.restaurant_id = ?'];
        $params = [$rid];

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

        $whereClause = 'WHERE ' . implode(' AND ', $where);

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

    public function getByStatut(string $statut): array {
        $rid = $this->requireRestaurant();
        return $this->query(
            "SELECT c.*, t.numero as table_numero
             FROM commandes c
             JOIN tables t ON t.id = c.table_id
             WHERE c.restaurant_id = ? AND c.statut = ?
             ORDER BY c.created_at ASC",
            [$rid, $statut]
        );
    }

    public function getTodayStats(): array {
        $rid = $this->requireRestaurant();
        $row = $this->queryOne(
            "SELECT
                COUNT(*) as nb_commandes,
                COALESCE(SUM(total), 0) as ca_total,
                COUNT(CASE WHEN statut = 'en_attente'     THEN 1 END) as en_attente,
                COUNT(CASE WHEN statut = 'en_preparation' THEN 1 END) as en_preparation
             FROM commandes
             WHERE restaurant_id = ? AND DATE(created_at) = CURDATE()",
            [$rid]
        );
        return $row ?: ['nb_commandes' => 0, 'ca_total' => 0, 'en_attente' => 0, 'en_preparation' => 0];
    }

    /** Recherche d une commande sans contexte resto (pour /order/status) */
    public function findByIdGlobal(int $id): array|false {
        return $this->queryOne("SELECT * FROM commandes WHERE id = ?", [$id]);
    }
}
