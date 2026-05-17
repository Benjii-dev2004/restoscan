<?php
/**
 * app/controllers/KitchenController.php
 * Controleur MVC - interface cuisine (scope restaurant)
 */

require_once APP_PATH . '/models/Order.php';
require_once APP_PATH . '/models/Setting.php';

class KitchenController extends Controller {

    public function index(): void {
        $this->requireAuth('cuisine|admin');
        $rid = $this->currentRestaurantId();

        $orderModel = new Order($rid);
        $orders     = $orderModel->getActiveForKitchen();

        $enAttente     = array_filter($orders, fn($o) => $o['statut'] === 'en_attente');
        $enPreparation = array_filter($orders, fn($o) => $o['statut'] === 'en_preparation');

        $settingModel = new Setting($rid);
        $this->render('kitchen/index', [
            'enAttente'     => array_values($enAttente),
            'enPreparation' => array_values($enPreparation),
            'user'          => $_SESSION['user'],
            'app_name'      => $settingModel->get('nom_restaurant', APP_NAME),
        ], 'kitchen');
    }

    public function update(string $id): void {
        $this->requireAuth('cuisine|admin');
        if (!$this->validateCsrfAjax()) {
            $this->json(['error' => 'Token CSRF invalide.'], 403);
        }

        $commandeId = (int) $id;
        $statut     = $_POST['statut'] ?? '';

        if (!$commandeId || !$statut) {
            $this->json(['error' => 'Parametres manquants.'], 400);
        }

        $orderModel = new Order($this->currentRestaurantId());
        $success    = $orderModel->updateStatut($commandeId, $statut);

        if (!$success) {
            $this->json(['error' => 'Mise a jour impossible.'], 400);
        }

        $this->json(['success' => true, 'statut' => $statut]);
    }

    public function poll(): void {
        $this->requireAuth('cuisine|admin');
        $orderModel = new Order($this->currentRestaurantId());
        $orders     = $orderModel->getActiveForKitchen();
        $this->json(['orders' => $orders]);
    }
}
