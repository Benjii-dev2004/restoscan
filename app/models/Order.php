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

    // ─── Reporting / analytics ─────────────────────────────────────────────

    /** Stats agregees sur une periode : CA, nb cmd, panier moyen */
    public function getStatsForPeriod(string $start, string $end): array {
        $rid = $this->requireRestaurant();
        $row = $this->queryOne(
            "SELECT
                COALESCE(SUM(total), 0) AS ca,
                COUNT(*)                AS nb_cmd,
                COALESCE(AVG(total), 0) AS panier_moyen
             FROM commandes
             WHERE restaurant_id = ?
               AND statut != 'annule'
               AND created_at BETWEEN ? AND ?",
            [$rid, $start, $end]
        );
        return $row ?: ['ca' => 0, 'nb_cmd' => 0, 'panier_moyen' => 0];
    }

    /**
     * Evolution du CA par bucket de temps (day | week | month | year).
     * Renvoie [ ['bucket' => '2026-05-01', 'ca' => 12000, 'nb' => 8], ... ]
     */
    public function getRevenueByBucket(string $start, string $end, string $grouping = 'day'): array {
        $rid = $this->requireRestaurant();

        $expr = match($grouping) {
            'year'  => "DATE_FORMAT(created_at, '%Y')",
            'month' => "DATE_FORMAT(created_at, '%Y-%m')",
            'week'  => "DATE_FORMAT(created_at, '%x-W%v')",
            default => "DATE(created_at)",
        };

        return $this->query(
            "SELECT {$expr} AS bucket,
                    COALESCE(SUM(total), 0) AS ca,
                    COUNT(*) AS nb
             FROM commandes
             WHERE restaurant_id = ?
               AND statut != 'annule'
               AND created_at BETWEEN ? AND ?
             GROUP BY bucket
             ORDER BY bucket ASC",
            [$rid, $start, $end]
        );
    }

    /** Top N plats avec CA genere sur une periode */
    public function getTopDishesForPeriod(string $start, string $end, int $limit = 10): array {
        $rid = $this->requireRestaurant();
        return $this->query(
            "SELECT m.nom,
                    SUM(ci.quantite)                     AS qte_totale,
                    SUM(ci.quantite * ci.prix_unitaire)  AS ca_genere,
                    COUNT(DISTINCT ci.commande_id)       AS nb_cmd
             FROM commande_items ci
             JOIN commandes  c ON c.id = ci.commande_id
             JOIN menu_items m ON m.id = ci.menu_item_id
             WHERE c.restaurant_id = ?
               AND c.statut != 'annule'
               AND c.created_at BETWEEN ? AND ?
             GROUP BY m.id, m.nom
             ORDER BY ca_genere DESC
             LIMIT ?",
            [$rid, $start, $end, $limit]
        );
    }

    /** Heures de pointe (0-23) */
    public function getHourlyDistribution(string $start, string $end): array {
        $rid = $this->requireRestaurant();
        return $this->query(
            "SELECT HOUR(created_at) AS heure,
                    COUNT(*)         AS nb_cmd,
                    COALESCE(SUM(total), 0) AS ca
             FROM commandes
             WHERE restaurant_id = ?
               AND statut != 'annule'
               AND created_at BETWEEN ? AND ?
             GROUP BY heure
             ORDER BY heure ASC",
            [$rid, $start, $end]
        );
    }

    /** Performance par table (numero, nb cmd, CA) */
    public function getStatsByTable(string $start, string $end): array {
        $rid = $this->requireRestaurant();
        return $this->query(
            "SELECT t.numero,
                    COUNT(c.id)                  AS nb_cmd,
                    COALESCE(SUM(c.total), 0)    AS ca
             FROM commandes c
             JOIN tables t ON t.id = c.table_id
             WHERE c.restaurant_id = ?
               AND c.statut != 'annule'
               AND c.created_at BETWEEN ? AND ?
             GROUP BY t.id, t.numero
             ORDER BY ca DESC",
            [$rid, $start, $end]
        );
    }

    /** Historique paginé pour /admin/orders */
    public function getHistoryPaginated(array $filters, int $page = 1, int $perPage = 50): array {
        $rid = $this->requireRestaurant();
        $where  = ['c.restaurant_id = ?'];
        $params = [$rid];

        if (!empty($filters['statut'])) {
            $where[]  = 'c.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['date_start'])) {
            $where[]  = 'c.created_at >= ?';
            $params[] = $filters['date_start'] . ' 00:00:00';
        }
        if (!empty($filters['date_end'])) {
            $where[]  = 'c.created_at <= ?';
            $params[] = $filters['date_end'] . ' 23:59:59';
        }
        if (!empty($filters['table_id'])) {
            $where[]  = 'c.table_id = ?';
            $params[] = (int) $filters['table_id'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $offset      = max(0, ($page - 1) * $perPage);

        $orders = $this->query(
            "SELECT c.*, t.numero AS table_numero
             FROM commandes c
             JOIN tables t ON t.id = c.table_id
             {$whereClause}
             ORDER BY c.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $total = $this->queryOne(
            "SELECT COUNT(*) AS cnt
             FROM commandes c
             {$whereClause}",
            $params
        );

        return [
            'orders'    => $orders,
            'total'     => (int) ($total['cnt'] ?? 0),
            'page'      => $page,
            'per_page'  => $perPage,
            'pages'     => max(1, (int) ceil(((int) ($total['cnt'] ?? 0)) / $perPage)),
        ];
    }

    /** Export complet pour CSV (peut etre lourd, attention sur 5 ans) */
    public function getOrdersForExport(string $start, string $end, ?string $statut = null): array {
        $rid = $this->requireRestaurant();
        $where  = ['c.restaurant_id = ?', 'c.created_at BETWEEN ? AND ?'];
        $params = [$rid, $start, $end];
        if ($statut) {
            $where[]  = 'c.statut = ?';
            $params[] = $statut;
        }
        $whereClause = 'WHERE ' . implode(' AND ', $where);

        return $this->query(
            "SELECT c.id, c.created_at, c.statut, c.total, c.notes,
                    t.numero AS table_numero,
                    GROUP_CONCAT(CONCAT(ci.quantite, 'x ', m.nom) SEPARATOR ' | ') AS items
             FROM commandes c
             JOIN tables t ON t.id = c.table_id
             LEFT JOIN commande_items ci ON ci.commande_id = c.id
             LEFT JOIN menu_items     m  ON m.id = ci.menu_item_id
             {$whereClause}
             GROUP BY c.id
             ORDER BY c.created_at DESC",
            $params
        );
    }
}
