<?php
/**
 * core/Model.php
 * Classe de base pour tous les modèles RESTOSCAN
 * Rôle : fournir l'accès PDO et les méthodes CRUD communes
 */

class Model {
    protected PDO $db;
    protected string $table = '';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /** Exécuter une requête préparée et retourner tous les résultats */
    protected function query(string $sql, array $params = []): array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Exécuter une requête préparée et retourner une seule ligne */
    protected function queryOne(string $sql, array $params = []): array|false {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /** Exécuter une requête préparée (INSERT / UPDATE / DELETE) */
    protected function execute(string $sql, array $params = []): bool {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /** Retourner le dernier ID inséré */
    protected function lastInsertId(): string {
        return $this->db->lastInsertId();
    }

    /** Trouver un enregistrement par ID */
    public function findById(int $id): array|false {
        return $this->queryOne(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        );
    }

    /** Retourner tous les enregistrements */
    public function findAll(string $orderBy = 'id ASC'): array {
        // Valider $orderBy : accepte "colonne ASC" ou "colonne DESC" uniquement
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]* (ASC|DESC)$/i', $orderBy)) {
            $orderBy = 'id ASC';
        }
        return $this->query("SELECT * FROM {$this->table} ORDER BY {$orderBy}");
    }

    /** Supprimer un enregistrement par ID */
    public function delete(int $id): bool {
        return $this->execute(
            "DELETE FROM {$this->table} WHERE id = ?",
            [$id]
        );
    }

    /** Compter les enregistrements */
    public function count(string $where = '', array $params = []): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        $row = $this->queryOne($sql, $params);
        return (int) ($row['total'] ?? 0);
    }

    /** Démarrer une transaction PDO */
    public function beginTransaction(): void {
        $this->db->beginTransaction();
    }

    /** Valider une transaction */
    public function commit(): void {
        $this->db->commit();
    }

    /** Annuler une transaction */
    public function rollback(): void {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
}
