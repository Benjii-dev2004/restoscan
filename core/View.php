<?php
/**
 * core/View.php
 * Moteur de rendu des vues RESTOSCAN
 */

class View {

    public static function e(mixed $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function csrfField(): string {
        $token = $_SESSION[CSRF_TOKEN_NAME] ?? '';
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . self::e($token) . '">';
    }

    /**
     * URL absolue, prefixee automatiquement par /r/{slug} si on est
     * dans un contexte restaurant. Les chemins "publics" ou super-admin
     * ne sont pas prefixes.
     */
    public static function url(string $path = ''): string {
        $path = ltrim($path, '/');

        // Chemins toujours globaux (ne JAMAIS prefixer)
        $publicPrefixes = ['menu/', 'order/', 'superadmin', 'public/', 'qrcodes/'];
        foreach ($publicPrefixes as $p) {
            if ($path === rtrim($p, '/') || str_starts_with($path, $p)) {
                return BASE_URL . '/' . $path;
            }
        }

        // Si on est dans un contexte restaurant, prefixer
        if (Context::hasContext()) {
            return BASE_URL . '/r/' . Context::slug() . '/' . $path;
        }

        return BASE_URL . '/' . $path;
    }

    /** Asset public (sans prefixage slug) */
    public static function asset(string $path): string {
        return BASE_URL . '/public/' . ltrim($path, '/');
    }

    /** Formater un prix - utilise la devise du restaurant courant si possible */
    public static function price(float $amount): string {
        $devise = 'FCFA';
        if (Context::hasContext()) {
            try {
                require_once APP_PATH . '/models/Setting.php';
                $devise = (new Setting(Context::id()))->get('devise', 'FCFA');
            } catch (\Throwable $e) { /* fallback */ }
        }
        return number_format($amount, 0, ',', ' ') . ' ' . $devise;
    }

    public static function date(string $datetime, string $format = 'd/m/Y H:i'): string {
        if (!$datetime) return '—';
        return (new DateTime($datetime))->format($format);
    }

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
