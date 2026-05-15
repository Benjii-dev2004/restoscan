<?php
/**
 * app/models/OrderItem.php
 * Modèle MVC — lignes d'articles d'une commande
 * Rôle : accès BDD pour la table `commande_items`
 */

class OrderItem extends Model {
    protected string $table = 'commande_items';

    /** Insérer plusieurs articles — doit être appelé dans une transaction externe */
    public function createBulk(int $commandeId, array $items): bool {
        $sql = "INSERT INTO commande_items
                    (commande_id, menu_item_id, quantite, prix_unitaire, notes)
                VALUES (?, ?, ?, ?, ?)";

        foreach ($items as $item) {
            $ok = $this->execute($sql, [
                $commandeId,
                (int)   $item['menu_item_id'],
                (int)   $item['quantite'],
                (float) $item['prix_unitaire'],
                        $item['notes'] ?? '',
            ]);
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /** Items d'une commande avec le détail du plat */
    public function findByCommande(int $commandeId): array {
        return $this->query(
            "SELECT ci.*, m.nom, m.image, m.temps_preparation
             FROM commande_items ci
             JOIN menu_items m ON m.id = ci.menu_item_id
             WHERE ci.commande_id = ?",
            [$commandeId]
        );
    }
}
