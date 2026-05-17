<?php
/**
 * app/models/SuperAdmin.php
 * Modele MVC - comptes super-administrateurs (proprietaires de RESTOSCAN)
 * Pas de scoping par restaurant
 */

class SuperAdmin extends Model {
    protected string $table = 'super_admins';

    public function findByEmail(string $email): array|false {
        return $this->queryOne(
            "SELECT * FROM super_admins WHERE email = ?",
            [$email]
        );
    }

    public function authenticate(string $email, string $password): array|false {
        $sa = $this->findByEmail($email);
        if ($sa && password_verify($password, $sa['password'])) {
            return $sa;
        }
        return false;
    }

    public function createSuperAdmin(string $nom, string $email, string $password): int {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $this->execute(
            "INSERT INTO super_admins (nom, email, password, created_at)
             VALUES (?, ?, ?, NOW())",
            [$nom, $email, $hashed]
        );
        return (int) $this->lastInsertId();
    }
}
