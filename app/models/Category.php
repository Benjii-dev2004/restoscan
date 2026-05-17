<?php
/**
 * app/models/Category.php
 * Modele MVC - categories du menu (scope par restaurant_id)
 */

class Category extends Model {
    protected string $table = 'categories';

    public function findAll(string $orderBy = 'ordre ASC'): array {
        $rid = $this->requireRestaurant();
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]* (ASC|DESC)$/i', $orderBy)) {
            $orderBy = 'ordre ASC';
        }
        return $this->query(
            "SELECT * FROM categories WHERE restaurant_id = ? ORDER BY {$orderBy}",
            [$rid]
        );
    }

    public function create(string $nom, int $ordre, string $icone): int {
        $rid = $this->requireRestaurant();
        $this->execute(
            "INSERT INTO categories (restaurant_id, nom, ordre, icone)
             VALUES (?, ?, ?, ?)",
            [$rid, $nom, $ordre, $icone]
        );
        return (int) $this->lastInsertId();
    }

    public function update(int $id, string $nom, int $ordre, string $icone): bool {
        $rid = $this->requireRestaurant();
        return $this->execute(
            "UPDATE categories SET nom = ?, ordre = ?, icone = ?
             WHERE id = ? AND restaurant_id = ?",
            [$nom, $ordre, $icone, $id, $rid]
        );
    }

    /** Verifie si la categorie contient des plats */
    public function hasItems(int $id): bool {
        $rid = $this->requireRestaurant();
        $row = $this->queryOne(
            "SELECT COUNT(*) AS cnt FROM menu_items
             WHERE categorie_id = ? AND restaurant_id = ?",
            [$id, $rid]
        );
        return $row && (int) $row['cnt'] > 0;
    }

    public function getAllWithCount(): array {
        $rid = $this->requireRestaurant();
        return $this->query(
            "SELECT c.*, COUNT(m.id) as nb_plats
             FROM categories c
             LEFT JOIN menu_items m ON m.categorie_id = c.id AND m.disponible = 1
             WHERE c.restaurant_id = ?
             GROUP BY c.id
             ORDER BY c.ordre ASC",
            [$rid]
        );
    }
}
