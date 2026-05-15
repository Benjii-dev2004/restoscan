<?php
/**
 * app/models/Category.php
 * Modèle MVC — gestion des catégories du menu
 * Rôle : accès BDD pour la table `categories`
 */

class Category extends Model {
    protected string $table = 'categories';

    /** Toutes les catégories ordonnées */
    public function findAll(string $orderBy = 'ordre ASC'): array {
        return $this->query(
            "SELECT * FROM categories ORDER BY {$orderBy}"
        );
    }

    /** Créer une catégorie */
    public function create(string $nom, int $ordre, string $icone): int {
        $this->execute(
            "INSERT INTO categories (nom, ordre, icone) VALUES (?, ?, ?)",
            [$nom, $ordre, $icone]
        );
        return (int) $this->lastInsertId();
    }

    /** Modifier une catégorie */
    public function update(int $id, string $nom, int $ordre, string $icone): bool {
        return $this->execute(
            "UPDATE categories SET nom = ?, ordre = ?, icone = ? WHERE id = ?",
            [$nom, $ordre, $icone, $id]
        );
    }

    /** Verifie si une categorie contient des plats (ne peut pas etre supprimee) */
    public function hasItems(int $id): bool {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS cnt FROM menu_items WHERE categorie_id = ?",
            [$id]
        );
        return $row && (int) $row['cnt'] > 0;
    }

    /** Catégories avec le nombre de plats disponibles */
    public function getAllWithCount(): array {
        return $this->query(
            "SELECT c.*, COUNT(m.id) as nb_plats
             FROM categories c
             LEFT JOIN menu_items m ON m.categorie_id = c.id AND m.disponible = 1
             GROUP BY c.id
             ORDER BY c.ordre ASC"
        );
    }
}
