<?php
/**
 * app/controllers/OrderController.php
 * Contrôleur MVC — gestion des commandes client
 * Rôle : créer une commande depuis le panier JSON et retourner le statut
 */

require_once APP_PATH . '/models/Table.php';
require_once APP_PATH . '/models/MenuItem.php';
require_once APP_PATH . '/models/Order.php';
require_once APP_PATH . '/models/OrderItem.php';

class OrderController extends Controller {

    /** POST /order/create — créer une commande (AJAX JSON) */
    public function create(): void {
        try {
            $this->doCreate();
        } catch (\Throwable $e) {
            error_log('[RESTOSCAN] OrderController::create() uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->json([
                'error' => 'Erreur serveur inattendue.',
                'debug' => $e->getMessage(),   // temporaire — retirer après debug
            ], 500);
        }
    }

    /** Logique interne de create() — séparée pour pouvoir wrapper le tout */
    private function doCreate(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Méthode non autorisée.'], 405);
        }

        $rawBody = file_get_contents('php://input');
        $data    = json_decode($rawBody, true);

        if (!$data || empty($data['qr_token']) || empty($data['items'])) {
            $this->json(['error' => 'Données manquantes.'], 400);
        }

        // Valider la table
        $tableModel = new Table();
        $table      = $tableModel->findByToken($data['qr_token']);
        if (!$table) {
            $this->json(['error' => 'Table introuvable.'], 404);
        }

        // Valider et recalculer le total côté serveur (anti-fraude)
        $menuModel = new MenuItem();
        $items     = [];
        $total     = 0.0;

        foreach ($data['items'] as $item) {
            $menuItem = $menuModel->findById((int) $item['id']);
            if (!$menuItem || !$menuItem['disponible']) {
                continue; // ignorer les plats indisponibles
            }
            $quantite = max(1, (int) $item['quantite']);
            $total   += $menuItem['prix'] * $quantite;
            $items[]  = [
                'menu_item_id' => $menuItem['id'],
                'quantite'     => $quantite,
                'prix_unitaire'=> $menuItem['prix'],
                'notes'        => $this->sanitize($item['notes'] ?? ''),
            ];
        }

        if (empty($items)) {
            $this->json(['error' => 'Aucun article valide dans la commande.'], 400);
        }

        // Limiter la quantité max par article (anti-abus)
        foreach ($items as &$it) {
            $it['quantite'] = min($it['quantite'], 50);
        }
        unset($it);

        $notes      = $this->sanitize($data['notes'] ?? '');
        $orderModel = new Order();

        // Transaction : commande + articles + statut table sont atomiques
        try {
            $orderModel->beginTransaction();

            $commandeId = $orderModel->create($table['id'], $total, $notes);

            $orderItemModel = new OrderItem();
            if (!$orderItemModel->createBulk($commandeId, $items)) {
                throw new \RuntimeException('Échec insertion articles.');
            }

            $tableModel->updateStatut($table['id'], 'occupee');
            $orderModel->commit();
        } catch (\Throwable $e) {
            $orderModel->rollback();
            throw $e; // remonter au wrapper create() pour logging
        }

        $this->json([
            'success'     => true,
            'commande_id' => $commandeId,
            'total'       => $total,
        ]);
    }

    /** GET /order/status/{id} — retourner le statut JSON d'une commande */
    public function status(string $id): void {
        $commandeId = (int) $id;
        if ($commandeId <= 0) {
            $this->json(['error' => 'ID invalide.'], 400);
        }

        $orderModel = new Order();
        $commande   = $orderModel->findById($commandeId);

        if (!$commande) {
            $this->json(['error' => 'Commande introuvable.'], 404);
        }

        $this->json([
            'id'     => $commande['id'],
            'statut' => $commande['statut'],
            'label'  => View::statusLabel($commande['statut']),
            'total'  => $commande['total'],
        ]);
    }
}
