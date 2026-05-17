<?php
/**
 * app/models/Setting.php
 * Modele MVC - parametres par restaurant
 */

class Setting extends Model {
    protected string $table = 'settings';

    public function get(string $key, string $default = ''): string {
        $rid = $this->requireRestaurant();
        $row = $this->queryOne(
            "SELECT valeur FROM settings WHERE restaurant_id = ? AND cle = ?",
            [$rid, $key]
        );
        return $row ? $row['valeur'] : $default;
    }

    public function set(string $key, string $value): void {
        $rid = $this->requireRestaurant();
        $this->execute(
            "INSERT INTO settings (restaurant_id, cle, valeur)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)",
            [$rid, $key, $value]
        );
    }

    public function getAll(): array {
        $rid = $this->requireRestaurant();
        $rows   = $this->query(
            "SELECT cle, valeur FROM settings WHERE restaurant_id = ?",
            [$rid]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['cle']] = $row['valeur'];
        }
        return $result;
    }

    /** Seed des settings par defaut pour un nouveau restaurant */
    public function seedDefaults(int $restaurantId, string $nomResto): void {
        $defaults = [
            'nom_restaurant'     => $nomResto,
            'slogan'             => 'Bienvenue a notre table',
            'couleur_principale' => '#e85d04',
            'devise'             => 'FCFA',
            'ip_locale'          => '',
        ];
        foreach ($defaults as $cle => $val) {
            $this->execute(
                "INSERT INTO settings (restaurant_id, cle, valeur)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)",
                [$restaurantId, $cle, $val]
            );
        }
    }
}
