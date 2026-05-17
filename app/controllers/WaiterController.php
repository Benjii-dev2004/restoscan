<?php
/**
 * app/controllers/WaiterController.php
 * Controleur MVC - interface serveur (scope restaurant)
 */

require_once APP_PATH . '/models/Order.php';
require_once APP_PATH . '/models/OrderItem.php';
require_once APP_PATH . '/models/Setting.php';

class WaiterController extends Controller {

    public function index(): void {
        $this->requireAuth('serveur|admin');
        $rid = $this->currentRestaurantId();

        $orderModel = new Order($rid);
        $pret    = $orderModel->getByStatut('pret');
        $encours = $orderModel->getByStatut('en_preparation');

        foreach ($pret as &$order) {
            $itemModel      = new OrderItem();
            $order['items'] = $itemModel->findByCommande($order['id']);
        }
        foreach ($encours as &$order) {
            $itemModel      = new OrderItem();
            $order['items'] = $itemModel->findByCommande($order['id']);
        }

        $settingModel = new Setting($rid);
        $this->render('waiter/index', [
            'pret'    => $pret,
            'encours' => $encours,
            'user'    => $_SESSION['user'],
            'app_name'=> $settingModel->get('nom_restaurant', APP_NAME),
        ], 'waiter');
    }

    public function serve(string $id): void {
        $this->requireAuth('serveur|admin');
        if (!$this->validateCsrfAjax()) {
            $this->json(['error' => 'Token CSRF invalide.'], 403);
        }
        $orderModel = new Order($this->currentRestaurantId());
        $success    = $orderModel->updateStatut((int) $id, 'servi');
        $this->json(['success' => $success]);
    }

    public function poll(): void {
        $this->requireAuth('serveur|admin');
        $rid        = $this->currentRestaurantId();
        $orderModel = new Order($rid);
        $pret       = $orderModel->getByStatut('pret');

        foreach ($pret as &$order) {
            $itemModel      = new OrderItem();
            $order['items'] = $itemModel->findByCommande($order['id']);
        }

        $this->json(['orders' => $pret]);
    }
}
