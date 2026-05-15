<?php
/**
 * app/models/Setting.php
 * Modèle MVC — paramètres de l'application
 * Rôle : lire/écrire les paramètres clé-valeur dans la table `settings`
 */

class Setting extends Model {
    protected string $table = 'settings';

    public function get(string $key, string $default = ''): string {
        $row = $this->queryOne(
            "SELECT valeur FROM settings WHERE cle = ?", [$key]
        );
        return $row ? $row['valeur'] : $default;
    }

    public function set(string $key, string $value): void {
        $this->execute(
            "INSERT INTO settings (cle, valeur) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)",
            [$key, $value]
        );
    }

    public function getAll(): array {
        $rows   = $this->query("SELECT cle, valeur FROM settings");
        $result = [];
        foreach ($rows as $row) {
            $result[$row['cle']] = $row['valeur'];
        }
        return $result;
    }
}
