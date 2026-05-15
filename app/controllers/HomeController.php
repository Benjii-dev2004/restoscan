<?php
/**
 * app/controllers/HomeController.php
 * Contrôleur MVC — page d'accueil
 * Rôle : rediriger vers le bon tableau de bord selon le rôle
 */

require_once APP_PATH . '/models/User.php';

class HomeController extends Controller {

    public function index(): void {
        if (!isset($_SESSION['user'])) {
            $this->redirect('/auth/login');
        }

        $role = $_SESSION['user']['role'];
        match($role) {
            'admin'    => $this->redirect('/admin/dashboard'),
            'cuisine'  => $this->redirect('/kitchen'),
            'serveur'  => $this->redirect('/waiter'),
            default    => $this->redirect('/auth/login'),
        };
    }
}
