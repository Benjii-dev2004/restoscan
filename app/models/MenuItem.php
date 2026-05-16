<?php
/**
 * app/models/MenuItem.php
 * Modèle MVC — gestion des plats du menu
 * Rôle : accès BDD pour la table `menu_items`
 */

class MenuItem extends Model {
    protected string $table = 'menu_items';

    /** Tous les plats disponibles avec leur catégorie, groupés */
    public function getMenuByCategory(): array {
        $items = $this->query(
            "SELECT m.*, c.nom as categorie_nom, c.icone as categorie_icone
             FROM menu_items m
             JOIN categories c ON c.id = m.categorie_id
             WHERE m.disponible = 1
             ORDER BY c.ordre ASC, m.nom ASC"
        );

        // Grouper par catégorie
        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item['categorie_nom']][] = $item;
        }
        return $grouped;
    }

    /** Tous les plats pour l'admin (disponibles ou non) */
    public function getAllForAdmin(): array {
        return $this->query(
            "SELECT m.*, c.nom as categorie_nom
             FROM menu_items m
             JOIN categories c ON c.id = m.categorie_id
             ORDER BY c.ordre ASC, m.nom ASC"
        );
    }

    /** Créer un plat */
    public function create(array $data): int {
        $this->execute(
            "INSERT INTO menu_items
                (categorie_id, nom, description, prix, image, disponible, temps_preparation)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
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

    /** Mettre à jour un plat */
    public function update(int $id, array $data): bool {
        return $this->execute(
            "UPDATE menu_items
             SET categorie_id = ?, nom = ?, description = ?, prix = ?,
                 image = ?, disponible = ?, temps_preparation = ?
             WHERE id = ?",
            [
                (int)   $data['categorie_id'],
                        $data['nom'],
                        $data['description'] ?? '',
                (float) $data['prix'],
                        $data['image'] ?? '',
                (int)   $data['disponible'],
                (int)   ($data['temps_preparation'] ?? 15),
                        $id,
            ]
        );
    }

    /** Basculer la disponibilité d'un plat */
    public function toggleAvailability(int $id): bool {
        return $this->execute(
            "UPDATE menu_items SET disponible = 1 - disponible WHERE id = ?",
            [$id]
        );
    }

    /** Plats les plus commandés (pour stats) — hors commandes annulées */
    public function getTopItems(int $limit = 3): array {
        return $this->query(
            "SELECT m.nom, SUM(ci.quantite) as total_qte
             FROM commande_items ci
             JOIN menu_items m ON m.id = ci.menu_item_id
             JOIN commandes c  ON c.id = ci.commande_id
             WHERE c.statut != 'annule'
             GROUP BY m.id, m.nom
             ORDER BY total_qte DESC
             LIMIT ?",
            [$limit]
        );
    }
}
