<?php
/**
 * core/Context.php
 * Contexte multi-tenant : restaurant courant deduit du slug dans l URL.
 * Initialise par le Router au debut de chaque requete.
 */

class Context {

    private static ?array $restaurant = null;

    public static function setRestaurant(array $resto): void {
        self::$restaurant = $resto;
    }

    public static function clear(): void {
        self::$restaurant = null;
    }

    public static function hasContext(): bool {
        return self::$restaurant !== null;
    }

    public static function restaurant(): ?array {
        return self::$restaurant;
    }

    public static function slug(): string {
        return self::$restaurant['slug'] ?? '';
    }

    public static function name(): string {
        return self::$restaurant['nom'] ?? '';
    }

    public static function id(): int {
        return (int) (self::$restaurant['id'] ?? 0);
    }

    /** Construire le prefix URL du restaurant courant */
    public static function urlPrefix(): string {
        return self::hasContext() ? '/r/' . self::slug() : '';
    }
}
