<?php
/**
 * core/Router.php
 * Routeur frontal de l'application RESTOSCAN
 * Rôle : analyser l'URL, dispatcher vers le bon contrôleur et la bonne méthode
 */

class Router {
    private array $routes = [];

    /** Enregistrer une route GET */
    public function get(string $path, string $controllerAction): void {
        $this->routes['GET'][$path] = $controllerAction;
    }

    /** Enregistrer une route POST */
    public function post(string $path, string $controllerAction): void {
        $this->routes['POST'][$path] = $controllerAction;
    }

    /** Dispatcher la requête courante */
    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = $this->getUri();

        // Chercher une route correspondante
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $pattern => $controllerAction) {
                $params = $this->match($pattern, $uri);
                if ($params !== false) {
                    $this->callAction($controllerAction, $params);
                    return;
                }
            }
        }

        // Aucune route trouvée → 404
        $this->notFound();
    }

    /** Extraire l'URI propre depuis la requête */
    private function getUri(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Supprimer le préfixe BASE_URL si présent (sous-dossier)
        $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
        if ($basePath && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }
        // Supprimer les query strings
        $uri = strtok($uri, '?');
        return '/' . trim($uri, '/');
    }

    /**
     * Comparer un pattern de route à l'URI.
     * Les segments {param} sont capturés.
     * Retourne un tableau de paramètres ou false.
     */
    private function match(string $pattern, string $uri): array|false {
        // Convertir /menu/{qr_token} → regex /menu/([^/]+)
        $regex = preg_replace('/\{[a-zA-Z_]+\}/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            array_shift($matches); // retirer le match complet
            return $matches;
        }
        return false;
    }

    /** Instancier le contrôleur et appeler la méthode */
    private function callAction(string $controllerAction, array $params): void {
        // BUG-09 : valider le format ControllerName@methodName
        if (!str_contains($controllerAction, '@')) {
            $this->notFound();
            return;
        }
        [$controllerName, $method] = explode('@', $controllerAction, 2);
        $controllerFile = APP_PATH . '/controllers/' . $controllerName . '.php';

        if (!file_exists($controllerFile)) {
            $this->notFound();
            return;
        }

        require_once $controllerFile;

        if (!class_exists($controllerName)) {
            $this->notFound();
            return;
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $method)) {
            $this->notFound();
            return;
        }

        call_user_func_array([$controller, $method], $params);
    }

    /** Page 404 */
    private function notFound(): void {
        http_response_code(404);
        require_once APP_PATH . '/views/errors/404.php';
        exit;
    }
}
