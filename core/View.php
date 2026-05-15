<?php
/**
 * core/View.php
 * Moteur de rendu des vues RESTOSCAN
 * Rôle : fournir des helpers d'affichage utilisables dans toutes les vues PHP
 */

class View {

    /** Échapper une valeur pour l'affichage HTML */
    public static function e(mixed $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** Générer un champ CSRF caché */
    public static function csrfField(): string {
        $token = $_SESSION[CSRF_TOKEN_NAME] ?? '';
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . self::e($token) . '">';
    }

    /** Générer une URL absolue */
    public static function url(string $path = ''): string {
        return BASE_URL . '/' . ltrim($path, '/');
    }

    /** Générer le chemin vers un asset public */
    public static function asset(string $path): string {
        return BASE_URL . '/public/' . ltrim($path, '/');
    }

    /** Formater un prix en FCFA */
    public static function price(float $amount): string {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }

    /** Formater une date */
    public static function date(string $datetime, string $format = 'd/m/Y H:i'): string {
        if (!$datetime) return '—';
        return (new DateTime($datetime))->format($format);
    }

    /** Retourner la classe CSS selon le statut d'une commande */
    public static function statusClass(string $status): string {
        return match($status) {
            'en_attente'     => 'status--waiting',
            'en_preparation' => 'status--preparing',
            'pret'           => 'status--ready',
            'servi'          => 'status--served',
            'annule'         => 'status--cancelled',
            default          => 'status--unknown',
        };
    }

    /** Retourner le libellé français d'un statut */
    public static function statusLabel(string $status): string {
        return match($status) {
            'en_attente'     => 'En attente',
            'en_preparation' => 'En préparation',
            'pret'           => 'Prêt',
            'servi'          => 'Servi',
            'annule'         => 'Annulé',
            default          => $status,
        };
    }
}
