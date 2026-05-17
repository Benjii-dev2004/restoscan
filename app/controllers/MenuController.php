<?php
/**
 * app/controllers/MenuController.php
 * Controleur MVC - interface client (menu public via QR code)
 * Le qr_token identifie a la fois la table ET le restaurant.
 */

require_once APP_PATH . '/models/Table.php';
require_once APP_PATH . '/models/Category.php';
require_once APP_PATH . '/models/MenuItem.php';
require_once APP_PATH . '/models/Setting.php';

class MenuController extends Controller {

    public function show(string $qrToken): void {
        // Recherche globale par token : retourne aussi le statut du restaurant
        $tableModel = new Table();
        $table      = $tableModel->findByToken($qrToken);

        if (!$table) {
            http_response_code(404);
            $this->render('errors/invalid_qr');
            return;
        }

        // Bloquer si restaurant suspendu ou abonnement expire
        $expired = $table['abonnement_fin']
                && strtotime($table['abonnement_fin']) < time();
        if ($table['restaurant_statut'] !== 'actif' || $expired) {
            http_response_code(503);
            $this->render('errors/restaurant_unavailable');
            return;
        }

        $rid = (int) $table['restaurant_id'];

        $menuModel      = new MenuItem($rid);
        $menuByCategory = $menuModel->getMenuByCategory();

        $categoryModel = new Category($rid);
        $categories    = $categoryModel->findAll();

        $settingModel = new Setting($rid);
        $this->render('menu/show', [
            'table'          => $table,
            'menuByCategory' => $menuByCategory,
            'categories'     => $categories,
            'app_name'       => $settingModel->get('nom_restaurant', APP_NAME),
            'primary_color'  => $settingModel->get('couleur_principale', '#e85d04'),
            'devise'         => $settingModel->get('devise', 'FCFA'),
            'base_url'       => BASE_URL,
        ], 'client');
    }
}
