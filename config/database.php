<?php
/**
 * config/database.php
 * Connexion PDO à la base de données MySQL
 * Rôle : fournir une instance PDO singleton utilisée par tous les modèles
 */

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $host     = DB_HOST;
            $dbname   = DB_NAME;
            $user     = DB_USER;
            $password = DB_PASS;
            $charset  = 'utf8mb4';

            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $password, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode(['error' => 'Connexion base de données impossible.']));
            }
        }
        return self::$instance;
    }
}
