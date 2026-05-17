<?php
/**
 * core/Router.php
 * Routeur frontal RESTOSCAN (multi-tenant slug-aware)
 *
 * Detecte les URLs /r/{slug}/... :
 *  - charge le restaurant et l initialise dans Context
 *  - strippe le prefix avant le matching des routes
 *  - 404 immediat si le slug est inconnu
 */

class Router {
    private array $routes = [];

    public function get(string $path, string $controllerAction): void {
        $this->routes['GET'][$path] = $controllerAction;
    }

    public function post(string $path, string $controllerAction): void {
        $this->routes['POST'][$path] = $controllerAction;
    }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = $this->getUri();

        // Detection prefix /r/{slug}/...
        if (preg_match('#^/r/([a-z0-9][a-z0-9-]*)(/.*)?$#', $uri, $m)) {
            require_once APP_PATH . '/models/Restaurant.php';
            $resto = (new Restaurant())->findBySlug($m[1]);
            if (!$resto) {
                $this->notFound();
                return;
            }
            Context::setRestaurant($resto);
            $uri = $m[2] ?: '/';
        }

        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $pattern => $controllerAction) {
                $params = $this->match($pattern, $uri);
                if ($params !== false) {
                    $this->callAction($controllerAction, $params);
                    return;
                }
            }
        }

        $this->notFound();
    }

    private function getUri(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
        if ($basePath && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }
        $uri = strtok($uri, '?');
        return '/' . trim($uri, '/');
    }

    private function match(string $pattern, string $uri): array|false {
        $regex = preg_replace('/\{[a-zA-Z_]+\}/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $uri, $matches)) {
            array_shift($matches);
            return $matches;
        }
        return false;
    }

    private function callAction(string $controllerAction, array $params): void {
        if (!str_contains($controllerAction, '@')) {
            $this->notFound();
            return;
        }
        [$controllerName, $method] = explode('@', $controllerAction, 2);
        $controllerFile = APP_PATH . '/controllers/' . $controllerName . '.php';

        if (!file_exists($controllerFile)) { $this->notFound(); return; }
        require_once $controllerFile;
        if (!class_exists($controllerName)) { $this->notFound(); return; }

        $controller = new $controllerName();
        if (!method_exists($controller, $method)) { $this->notFound(); return; }

        call_user_func_array([$controller, $method], $params);
    }

    private function notFound(): void {
        http_response_code(404);
        require_once APP_PATH . '/views/errors/404.php';
        exit;
    }
}
