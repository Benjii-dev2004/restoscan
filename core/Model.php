<?php
/**
 * core/Model.php
 * Classe de base pour tous les modeles RESTOSCAN (multi-tenant)
 * Tout modele lie a un restaurant doit recevoir son restaurantId au constructeur.
 */

class Model {
    protected PDO $db;
    protected string $table = '';
    protected ?int $restaurantId = null;

    public function __construct(?int $restaurantId = null) {
        $this->db = Database::getInstance();
        $this->restaurantId = $restaurantId;
    }

    /** Definir le restaurant courant (utile apres construction) */
    public function setRestaurant(int $id): void {
        $this->restaurantId = $id;
    }

    /** Retourne l ID du restaurant courant ou leve une exception */
    protected function requireRestaurant(): int {
        if ($this->restaurantId === null) {
            throw new \LogicException(static::class . ' requires a restaurant_id context');
        }
        return $this->restaurantId;
    }

    /** Requete SELECT preparee, retourne toutes les lignes */
    protected function query(string $sql, array $params = []): array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Requete SELECT preparee, retourne une seule ligne */
    protected function queryOne(string $sql, array $params = []): array|false {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /** Requete INSERT/UPDATE/DELETE preparee */
    protected function execute(string $sql, array $params = []): bool {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /** Dernier ID insere */
    protected function lastInsertId(): string {
        return $this->db->lastInsertId();
    }

    /** Trouver une ligne par ID (scope automatique si restaurantId defini) */
    public function findById(int $id): array|false {
        if ($this->restaurantId !== null) {
            return $this->queryOne(
                "SELECT * FROM {$this->table} WHERE id = ? AND restaurant_id = ?",
                [$id, $this->restaurantId]
            );
        }
        return $this->queryOne(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        );
    }

    /** Toutes les lignes (scope automatique si restaurantId defini) */
    public function findAll(string $orderBy = 'id ASC'): array {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]* (ASC|DESC)$/i', $orderBy)) {
            $orderBy = 'id ASC';
        }
        if ($this->restaurantId !== null) {
            return $this->query(
                "SELECT * FROM {$this->table} WHERE restaurant_id = ? ORDER BY {$orderBy}",
                [$this->restaurantId]
            );
        }
        return $this->query("SELECT * FROM {$this->table} ORDER BY {$orderBy}");
    }

    /** Supprimer une ligne par ID (scope automatique si restaurantId defini) */
    public function delete(int $id): bool {
        if ($this->restaurantId !== null) {
            return $this->execute(
                "DELETE FROM {$this->table} WHERE id = ? AND restaurant_id = ?",
                [$id, $this->restaurantId]
            );
        }
        return $this->execute(
            "DELETE FROM {$this->table} WHERE id = ?",
            [$id]
        );
    }

    /** Compter les enregistrements */
    public function count(string $where = '', array $params = []): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        if ($where) $sql .= " WHERE {$where}";
        $row = $this->queryOne($sql, $params);
        return (int) ($row['total'] ?? 0);
    }

    /** Transaction PDO (publiques pour usage cross-controller) */
    public function beginTransaction(): void { $this->db->beginTransaction(); }
    public function commit(): void            { $this->db->commit(); }
    public function rollback(): void {
        if ($this->db->inTransaction()) $this->db->rollBack();
    }
}
