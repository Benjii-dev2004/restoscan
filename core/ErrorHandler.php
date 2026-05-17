<?php
/**
 * core/ErrorHandler.php
 *
 * Gestion centralisee des erreurs PHP.
 * Capture les warnings, errors, exceptions et fatal errors.
 * Logge tout dans logs/error.log avec un contexte riche.
 * Affiche une page d erreur propre en prod, ou les details en dev.
 */

class ErrorHandler {

    private static string $logFile;

    public static function register(): void {
        self::$logFile = ROOT_PATH . '/logs/error.log';

        // Erreurs PHP classiques (E_WARNING, E_NOTICE, etc.)
        set_error_handler([self::class, 'handlePhpError']);

        // Exceptions non attrapees
        set_exception_handler([self::class, 'handleException']);

        // Erreurs fatales (parse, type, memory)
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handlePhpError(int $level, string $message, string $file, int $line): bool {
        // Ne pas capturer les erreurs supprimees avec @
        if (!(error_reporting() & $level)) return false;

        self::log([
            'type'    => self::levelName($level),
            'level'   => $level,
            'message' => $message,
            'file'    => $file,
            'line'    => $line,
        ]);

        // Pour les erreurs serieuses, lever en exception pour stopper proprement
        if (in_array($level, [E_ERROR, E_CORE_ERROR, E_USER_ERROR], true)) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }

        return true; // empeche le gestionnaire PHP par defaut
    }

    public static function handleException(\Throwable $e): void {
        self::log([
            'type'    => 'Exception',
            'class'   => get_class($e),
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ]);

        if (defined('ENV') && ENV === 'development') {
            // Dev : afficher les details
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(500);
            echo "ERREUR : " . $e->getMessage() . "\n";
            echo "Fichier : " . $e->getFile() . ":" . $e->getLine() . "\n\n";
            echo $e->getTraceAsString();
        } else {
            // Prod : page generique
            http_response_code(500);
            $errorView = APP_PATH . '/views/errors/500.php';
            if (file_exists($errorView)) {
                require $errorView;
            } else {
                echo "Une erreur est survenue. L equipe a ete alertee.";
            }
        }
        exit;
    }

    public static function handleShutdown(): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_PARSE, E_COMPILE_ERROR], true)) {
            self::log([
                'type'    => 'FatalError',
                'message' => $error['message'],
                'file'    => $error['file'],
                'line'    => $error['line'],
            ]);

            if (!defined('ENV') || ENV !== 'development') {
                http_response_code(500);
                echo "Une erreur grave est survenue.";
            }
        }
    }

    /** Ecrire un log structure (JSON sur 1 ligne, parsable) */
    private static function log(array $data): void {
        $entry = array_merge([
            'timestamp' => date('c'),
            'url'       => ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . ($_SERVER['REQUEST_URI'] ?? ''),
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_id'   => $_SESSION['user']['id']        ?? null,
            'resto_id'  => $_SESSION['user']['restaurant_id'] ?? null,
            'sa_id'     => $_SESSION['superadmin']['id']  ?? null,
        ], $data);

        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        @file_put_contents(
            self::$logFile,
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    private static function levelName(int $level): string {
        return match($level) {
            E_ERROR             => 'E_ERROR',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_STRICT            => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
            default             => 'E_UNKNOWN(' . $level . ')',
        };
    }

    /** Lire les N dernieres entrees du log (pour la page health) */
    public static function recentErrors(int $limit = 50): array {
        $file = ROOT_PATH . '/logs/error.log';
        if (!file_exists($file)) return [];

        $entries = [];
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return [];

        // Lire les dernieres lignes en partant de la fin
        $lines = array_slice($lines, -$limit);
        foreach (array_reverse($lines) as $line) {
            $parsed = json_decode($line, true);
            if ($parsed) $entries[] = $parsed;
        }
        return $entries;
    }

    public static function todayCount(): int {
        $file = ROOT_PATH . '/logs/error.log';
        if (!file_exists($file)) return 0;
        $today = date('Y-m-d');
        $count = 0;
        $handle = @fopen($file, 'r');
        if (!$handle) return 0;
        while (($line = fgets($handle)) !== false) {
            if (str_contains($line, "\"timestamp\":\"{$today}")) {
                $count++;
            }
        }
        fclose($handle);
        return $count;
    }
}
