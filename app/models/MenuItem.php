<?php
/**
 * app/models/MenuItem.php
 * Modele MVC - plats du menu (scope par restaurant_id)
 */

class MenuItem extends Model {
    protected string $table = 'menu_items';

    /** Plats disponibles groupes par categorie */
    public function getMenuByCategory(): array {
        $rid = $this->requireRestaurant();
        $items = $this->query(
            "SELECT m.*, c.nom as categorie_nom, c.icone as categorie_icone
             FROM menu_items m
             JOIN categories c ON c.id = m.categorie_id
             WHERE m.restaurant_id = ? AND m.disponible = 1
             ORDER BY c.ordre ASC, m.nom ASC",
            [$rid]
        );

        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item['categorie_nom']][] = $item;
        }
        return $grouped;
    }

    /** Tous les plats pour l admin (disponibles ou non) */
    public function getAllForAdmin(): array {
        $rid = $this->requireRestaurant();
        return $this->query(
            "SELECT m.*, c.nom as categorie_nom
             FROM menu_items m
             JOIN categories c ON c.id = m.categorie_id
             WHERE m.restaurant_id = ?
             ORDER BY c.ordre ASC, m.nom ASC",
            [$rid]
        );
    }

    public function create(array $data): int {
        $rid = $this->requireRestaurant();
        $this->execute(
            "INSERT INTO menu_items
                (restaurant_id, categorie_id, nom, description, prix, image,
                 disponible, temps_preparation)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $rid,
                (int)   $data['categorie_id'],
                        $data['nom'],
                        $data['description'] ?? '',
                (float) $data['prix'],
                        $data['image'] ?? '',
                        1,
                (int)   ($data['temps_preparation'] ?? 15),
            ]
        );
        return (int) $this->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $rid = $this->requireRestaurant();
        return $this->execute(
            "UPDATE menu_items
             SET categorie_id = ?, nom = ?, description = ?, prix = ?,
                 image = ?, disponible = ?, temps_preparation = ?
             WHERE id = ? AND restaurant_id = ?",
            [
                (int)   $data['categorie_id'],
                        $data['nom'],
                        $data['description'] ?? '',
                (float) $data['prix'],
                        $data['image'] ?? '',
                (int)   $data['disponible'],
                (int)   ($data['temps_preparation'] ?? 15),
                        $id, $rid,
            ]
        );
    }

    public function toggleAvailability(int $id): bool {
        $rid = $this->requireRestaurant();
        return $this->execute(
            "UPDATE menu_items SET disponible = 1 - disponible
             WHERE id = ? AND restaurant_id = ?",
            [$id, $rid]
        );
    }

    /** Plats les plus commandes (hors annule) - scope restaurant */
    public function getTopItems(int $limit = 3): array {
        $rid = $this->requireRestaurant();
        return $this->query(
            "SELECT m.nom, SUM(ci.quantite) as total_qte
             FROM commande_items ci
             JOIN menu_items m ON m.id = ci.menu_item_id
             JOIN commandes c  ON c.id = ci.commande_id
             WHERE c.restaurant_id = ? AND c.statut != 'annule'
             GROUP BY m.id, m.nom
             ORDER BY total_qte DESC
             LIMIT ?",
            [$rid, $limit]
        );
    }
}
