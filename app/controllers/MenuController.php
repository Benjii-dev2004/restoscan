<?php
/**
 * app/controllers/MenuController.php
 * Contrôleur MVC — interface client (menu public via QR code)
 * Rôle : afficher le menu interactif d'une table identifiée par son token QR
 */

require_once APP_PATH . '/models/Table.php';
require_once APP_PATH . '/models/Category.php';
require_once APP_PATH . '/models/MenuItem.php';
require_once APP_PATH . '/models/Setting.php';

class MenuController extends Controller {

    public function show(string $qrToken): void {
        $tableModel = new Table();
        $table      = $tableModel->findByToken($qrToken);

        if (!$table) {
            http_response_code(404);
            $this->render('errors/invalid_qr');
            return;
        }

        $menuModel  = new MenuItem();
        $menuByCategory = $menuModel->getMenuByCategory();

        $categoryModel = new Category();
        $categories    = $categoryModel->findAll();

        $settingModel = new Setting();
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
