<?php
/**
 * core/Controller.php
 * Classe de base pour tous les controleurs RESTOSCAN (multi-tenant slug-aware)
 */

class Controller {

    protected function render(string $view, array $data = [], string $layout = ''): void {
        unset($data['content']);
        extract($data);
        $viewFile = APP_PATH . '/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            die("Vue introuvable : {$view}");
        }

        if ($layout) {
            ob_start();
            require $viewFile;
            $content = ob_get_clean();
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

    protected function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Redirection - prefixe automatiquement par /r/{slug} via View::url() */
    protected function redirect(string $path): void {
        header('Location: ' . View::url(ltrim($path, '/')));
        exit;
    }

    /**
     * Auth check + role check + slug match check + subscription check.
     * $role : 'admin' ou 'admin|cuisine|serveur' (separateur |)
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
        // Verifier que le slug URL appartient bien au restaurant de l utilisateur
        if (Context::hasContext()
            && Context::id() !== (int) $_SESSION['user']['restaurant_id']) {
            // L user tente d acceder a un autre restaurant -> deconnecter
            session_destroy();
            header('Location: ' . BASE_URL . '/r/' . Context::slug() . '/auth/login');
            exit;
        }
        $this->checkSubscription();
    }

    protected function currentRestaurantId(): int {
        $id = (int) ($_SESSION['user']['restaurant_id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/auth/login');
        }
        return $id;
    }

    protected function checkSubscription(): void {
        require_once APP_PATH . '/models/Restaurant.php';
        $rid = (int) ($_SESSION['user']['restaurant_id'] ?? 0);
        if ($rid <= 0) return;

        $resto = (new Restaurant())->findByIdGlobal($rid);
        if (!$resto) {
            session_destroy();
            $this->redirect('/auth/login');
        }

        $expired = $resto['abonnement_fin']
                && strtotime($resto['abonnement_fin']) < time();

        if ($resto['statut'] !== 'actif' || $expired) {
            if ($expired && $resto['statut'] === 'actif') {
                (new Restaurant())->setStatut($rid, 'expire');
                $resto['statut'] = 'expire';
            }
            $this->render('errors/subscription_blocked', [
                'restaurant' => $resto,
                'app_name'   => $resto['nom'],
            ]);
            exit;
        }
    }

    protected function generateCsrf(): string {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    protected function validateCsrf(): bool {
        $token = $_POST[CSRF_TOKEN_NAME] ?? '';
        return isset($_SESSION[CSRF_TOKEN_NAME])
            && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    protected function validateCsrfAjax(): bool {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return isset($_SESSION[CSRF_TOKEN_NAME])
            && $token !== ''
            && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    protected function isAjax(): bool {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    protected function sanitize(string $value): string {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
}
