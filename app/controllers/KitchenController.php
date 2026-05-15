<?php
/**
 * app/controllers/KitchenController.php
 * Contrôleur MVC — interface cuisine (tableau Kanban)
 * Rôle : afficher les commandes en cours, permettre les changements de statut
 */

require_once APP_PATH . '/models/Order.php';
require_once APP_PATH . '/models/Setting.php';

class KitchenController extends Controller {

    public function index(): void {
        $this->requireAuth('cuisine');

        $orderModel = new Order();
        $orders     = $orderModel->getActiveForKitchen();

        // Séparer par statut pour l'affichage Kanban
        $enAttente     = array_filter($orders, fn($o) => $o['statut'] === 'en_attente');
        $enPreparation = array_filter($orders, fn($o) => $o['statut'] === 'en_preparation');

        $settingModel = new Setting();
        $this->render('kitchen/index', [
            'enAttente'     => array_values($enAttente),
            'enPreparation' => array_values($enPreparation),
            'user'          => $_SESSION['user'],
            'app_name'      => $settingModel->get('nom_restaurant', APP_NAME),
        ], 'kitchen');
    }

    /** POST /kitchen/update/{id} — changer le statut d'une commande (AJAX) */
    public function update(string $id): void {
        $this->requireAuth('cuisine');
        // SEC-05 : valider le token CSRF envoye en header par le JS
        if (!$this->validateCsrfAjax()) {
            $this->json(['error' => 'Token CSRF invalide.'], 403);
        }

        $commandeId = (int) $id;
        $statut     = $_POST['statut'] ?? '';

        if (!$commandeId || !$statut) {
            $this->json(['error' => 'Paramètres manquants.'], 400);
        }

        $orderModel = new Order();
        $success    = $orderModel->updateStatut($commandeId, $statut);

        if (!$success) {
            $this->json(['error' => 'Mise à jour impossible.'], 400);
        }

        $this->json(['success' => true, 'statut' => $statut]);
    }

    /** GET /kitchen/poll — retourner les commandes actives en JSON (short polling) */
    public function poll(): void {
        $this->requireAuth('cuisine');

        $orderModel = new Order();
        $orders     = $orderModel->getActiveForKitchen();

        $this->json(['orders' => $orders]);
    }
}
