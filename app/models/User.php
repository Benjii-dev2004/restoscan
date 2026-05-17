<?php
/**
 * app/models/User.php
 * Modele MVC - utilisateurs (admin, cuisine, serveur) d un restaurant
 * Scope par restaurant_id
 */

class User extends Model {
    protected string $table = 'users';

    /** Trouver un user par email (recherche GLOBALE pour le login) */
    public function findByEmailGlobal(string $email): array|false {
        return $this->queryOne("SELECT * FROM users WHERE email = ?", [$email]);
    }

    /** Authentification globale (le restaurant_id est decouvert via l email) */
    public function authenticate(string $email, string $password): array|false {
        $user = $this->findByEmailGlobal($email);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    /** Creer un user dans le restaurant courant */
    public function create(string $nom, string $email, string $password, string $role): int {
        $rid    = $this->requireRestaurant();
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $this->execute(
            "INSERT INTO users (restaurant_id, nom, email, password, role, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$rid, $nom, $email, $hashed, $role]
        );
        return (int) $this->lastInsertId();
    }

    /** Creer un user pour un restaurant donne (utilise par SuperAdmin a la creation) */
    public function createForRestaurant(int $restaurantId, string $nom, string $email, string $password, string $role): int {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $this->execute(
            "INSERT INTO users (restaurant_id, nom, email, password, role, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$restaurantId, $nom, $email, $hashed, $role]
        );
        return (int) $this->lastInsertId();
    }

    /** Tous les users du restaurant courant (sans password) */
    public function findAll(string $orderBy = 'nom ASC'): array {
        $rid = $this->requireRestaurant();
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]* (ASC|DESC)$/i', $orderBy)) {
            $orderBy = 'nom ASC';
        }
        return $this->query(
            "SELECT id, restaurant_id, nom, email, role, created_at
             FROM users WHERE restaurant_id = ?
             ORDER BY {$orderBy}",
            [$rid]
        );
    }
}
