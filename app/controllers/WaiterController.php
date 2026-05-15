<?php
/**
 * app/controllers/WaiterController.php
 * Contrôleur MVC — interface serveur en salle
 * Rôle : afficher les commandes prêtes à servir, marquer comme servies
 */

require_once APP_PATH . '/models/Order.php';
require_once APP_PATH . '/models/OrderItem.php';
require_once APP_PATH . '/models/Setting.php';

class WaiterController extends Controller {

    public function index(): void {
        $this->requireAuth('serveur|admin');

        $orderModel = new Order();

        // Commandes prêtes à servir
        $pret  = $orderModel->getByStatut('pret');
        // Commandes en cours (info seulement)
        $encours = $orderModel->getByStatut('en_preparation');

        foreach ($pret as &$order) {
            $itemModel    = new OrderItem();
            $order['items'] = $itemModel->findByCommande($order['id']);
        }
        foreach ($encours as &$order) {
            $itemModel    = new OrderItem();
            $order['items'] = $itemModel->findByCommande($order['id']);
        }

        $settingModel = new Setting();
        $this->render('waiter/index', [
            'pret'    => $pret,
            'encours' => $encours,
            'user'    => $_SESSION['user'],
            'app_name'=> $settingModel->get('nom_restaurant', APP_NAME),
        ], 'waiter');
    }

    /** POST /waiter/serve/{id} — marquer une commande comme servie (AJAX) */
    public function serve(string $id): void {
        $this->requireAuth('serveur|admin');
        // SEC-05 : valider le token CSRF envoye en header par le JS
        if (!$this->validateCsrfAjax()) {
            $this->json(['error' => 'Token CSRF invalide.'], 403);
        }

        $orderModel = new Order();
        $success    = $orderModel->updateStatut((int) $id, 'servi');

        $this->json(['success' => $success]);
    }

    /** GET /waiter/poll — retourner les commandes prêtes en JSON */
    public function poll(): void {
        $this->requireAuth('serveur|admin');

        $orderModel = new Order();
        $pret       = $orderModel->getByStatut('pret');

        foreach ($pret as &$order) {
            $itemModel      = new OrderItem();
            $order['items'] = $itemModel->findByCommande($order['id']);
        }

        $this->json(['orders' => $pret]);
    }
}
