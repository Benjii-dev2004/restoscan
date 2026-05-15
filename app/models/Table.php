<?php
/**
 * app/models/Table.php
 * Modèle MVC — gestion des tables du restaurant
 * Rôle : accès BDD pour la table `tables` (CRUD + QR codes)
 */

class Table extends Model {
    protected string $table = 'tables';

    /** Trouver une table par son token QR code */
    public function findByToken(string $token): array|false {
        return $this->queryOne(
            "SELECT * FROM tables WHERE qr_token = ?",
            [$token]
        );
    }

    /** Créer une nouvelle table avec un token UUID aléatoire */
    public function create(int $numero, int $capacite): array|false {
        $token = bin2hex(random_bytes(32));
        $this->execute(
            "INSERT INTO tables (numero, qr_token, capacite, statut, created_at)
             VALUES (?, ?, ?, 'libre', NOW())",
            [$numero, $token, $capacite]
        );
        $id = (int) $this->lastInsertId();
        return $this->findById($id);
    }

    /** Mettre à jour le statut d'une table */
    public function updateStatut(int $id, string $statut): bool {
        $allowed = ['libre', 'occupee', 'reservee'];
        if (!in_array($statut, $allowed, true)) {
            return false;
        }
        return $this->execute(
            "UPDATE tables SET statut = ? WHERE id = ?",
            [$statut, $id]
        );
    }

    /** Obtenir toutes les tables avec leur statut courant */
    public function getAllWithStatus(): array {
        return $this->query(
            "SELECT t.*,
                    COUNT(c.id) as commandes_actives
             FROM tables t
             LEFT JOIN commandes c ON c.table_id = t.id
                 AND c.statut NOT IN ('servi', 'annule')
             GROUP BY t.id
             ORDER BY t.numero ASC"
        );
    }

    /** Verifie si une table a des commandes actives (ne peut pas etre supprimee) */
    public function hasActiveOrders(int $id): bool {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS cnt FROM commandes
             WHERE table_id = ? AND statut NOT IN ('servi', 'annule')",
            [$id]
        );
        return $row && (int) $row['cnt'] > 0;
    }

    /**
     * Resynchroniser le champ `statut` de toutes les tables
     * en fonction des commandes réellement actives.
     * À appeler au chargement du dashboard pour corriger d'éventuels écarts.
     */
    public function syncStatuts(): void {
        // Passer à 'libre' toutes les tables qui n'ont aucune commande active
        $this->execute(
            "UPDATE tables t
             SET t.statut = 'libre'
             WHERE t.statut = 'occupee'
               AND NOT EXISTS (
                   SELECT 1 FROM commandes c
                   WHERE c.table_id = t.id
                     AND c.statut NOT IN ('servi', 'annule')
               )"
        );
        // Passer à 'occupee' toutes les tables qui ont au moins une commande active
        $this->execute(
            "UPDATE tables t
             SET t.statut = 'occupee'
             WHERE t.statut = 'libre'
               AND EXISTS (
                   SELECT 1 FROM commandes c
                   WHERE c.table_id = t.id
                     AND c.statut NOT IN ('servi', 'annule')
               )"
        );
    }
}
