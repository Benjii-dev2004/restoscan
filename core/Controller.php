<?php
/**
 * core/Controller.php
 * Classe de base pour tous les contrôleurs RESTOSCAN
 * Rôle : fournir les méthodes communes (rendu de vue, redirection, JSON, CSRF)
 */

class Controller {

    /** Rendre une vue avec ses données */
    protected function render(string $view, array $data = [], string $layout = ''): void {
        // BUG-21 : éviter que $data['content'] n'écrase la variable $content du layout
        unset($data['content']);
        extract($data);
        $viewFile = APP_PATH . '/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            die("Vue introuvable : {$view}");
        }

        if ($layout) {
            // Capturer le contenu de la vue dans un buffer
            ob_start();
            require $viewFile;
            $content = ob_get_clean();

            // Injecter dans le layout
            $layoutFile = APP_PATH . '/views/layouts/' . $layout . '.php';
            if (!file_exists($layoutFile)) {
                http_response_code(500);
                die("Layout introuvable : {$layout}");
            }
            require $layoutFile;
        } else {
            require $viewFile;
        }
    }

    /** Retourner une réponse JSON (pour les endpoints AJAX) */
    protected function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Rediriger vers une URL */
    protected function redirect(string $path): void {
        header('Location: ' . BASE_URL . $path);
        exit;
    }

    /** Vérifier que l'utilisateur est authentifié
     *  $role peut être une string simple ('admin') ou plusieurs ('admin|serveur')
     */
    protected function requireAuth(string $role = ''): void {
        if (!isset($_SESSION['user'])) {
            $this->redirect('/auth/login');
        }
        if ($role) {
            $allowed = explode('|', $role);
            if (!in_array($_SESSION['user']['role'], $allowed, true)) {
                $this->redirect('/');
            }
        }
    }

    /** Générer un token CSRF et le stocker en session */
    protected function generateCsrf(): string {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /** Valider le token CSRF d'un formulaire POST */
    protected function validateCsrf(): bool {
        $token = $_POST[CSRF_TOKEN_NAME] ?? '';
        return isset($_SESSION[CSRF_TOKEN_NAME])
            && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    /** Valider le token CSRF envoyé en header AJAX (X-Csrf-Token) */
    protected function validateCsrfAjax(): bool {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return isset($_SESSION[CSRF_TOKEN_NAME])
            && $token !== ''
            && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    /** Retourner true si la requête est AJAX */
    protected function isAjax(): bool {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /** Nettoyer et valider une entrée */
    protected function sanitize(string $value): string {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
}
