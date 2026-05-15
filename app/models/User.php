<?php
/**
 * app/models/User.php
 * Modèle MVC — gestion des utilisateurs (admin, cuisine, serveur)
 * Rôle : accès BDD pour la table `users`
 */

class User extends Model {
    protected string $table = 'users';

    /** Trouver un utilisateur par email */
    public function findByEmail(string $email): array|false {
        return $this->queryOne(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );
    }

    /** Vérifier les identifiants de connexion */
    public function authenticate(string $email, string $password): array|false {
        $user = $this->findByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    /** Créer un utilisateur */
    public function create(string $nom, string $email, string $password, string $role): int {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $this->execute(
            "INSERT INTO users (nom, email, password, role, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$nom, $email, $hashed, $role]
        );
        return (int) $this->lastInsertId();
    }

    /** Tous les utilisateurs (sans le mot de passe) */
    public function findAll(string $orderBy = 'nom ASC'): array {
        return $this->query(
            "SELECT id, nom, email, role, created_at FROM users ORDER BY {$orderBy}"
        );
    }
}
