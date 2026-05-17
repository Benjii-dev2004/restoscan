<?php
/**
 * app/controllers/HomeController.php
 * Page d accueil : redirige vers le dashboard du role connecte.
 *
 * Si pas de contexte restaurant et pas connecte -> 404 (on ne revele rien
 * sur la nature multi-tenant de l app).
 */

require_once APP_PATH . '/models/Restaurant.php';

class HomeController extends Controller {

    public function index(): void {
        // Pas connecte
        if (!isset($_SESSION['user'])) {
            if (Context::hasContext()) {
                // /r/{slug}/ sans session -> page de login du resto
                $this->redirect('/auth/login');
            }
            // Pas de slug ni session -> 404 (URL racine est privee)
            http_response_code(404);
            require_once APP_PATH . '/views/errors/404.php';
            exit;
        }

        // Connecte : reconstruire l URL avec le slug du restaurant de l user
        $resto = (new Restaurant())->findByIdGlobal((int) $_SESSION['user']['restaurant_id']);
        if (!$resto) {
            session_destroy();
            http_response_code(404);
            require_once APP_PATH . '/views/errors/404.php';
            exit;
        }
        $slug = $resto['slug'];
        $role = $_SESSION['user']['role'];

        $dest = match($role) {
            'admin'    => "/r/{$slug}/admin/dashboard",
            'cuisine'  => "/r/{$slug}/kitchen",
            'serveur'  => "/r/{$slug}/waiter",
            default    => "/r/{$slug}/auth/login",
        };

        header('Location: ' . BASE_URL . $dest);
        exit;
    }
}
