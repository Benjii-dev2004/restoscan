<?php
/**
 * app/controllers/OrderController.php
 * Controleur MVC - creation et suivi des commandes client (multi-tenant)
 * Le restaurant_id est deduit du qr_token de la table.
 */

require_once APP_PATH . '/models/Table.php';
require_once APP_PATH . '/models/MenuItem.php';
require_once APP_PATH . '/models/Order.php';
require_once APP_PATH . '/models/OrderItem.php';
require_once APP_PATH . '/models/RateLimit.php';

class OrderController extends Controller {

    /** POST /order/create */
    public function create(): void {
        try {
            $this->doCreate();
        } catch (\Throwable $e) {
            error_log('[RESTOSCAN] OrderController::create() uncaught: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->json(['error' => 'Erreur serveur inattendue.'], 500);
        }
    }

    private function doCreate(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Methode non autorisee.'], 405);
        }

        // RATE LIMIT global par IP (DoS protection)
        // Max 20 commandes par IP / minute (generous pour un vrai resto plein)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rl = new RateLimit();
        if (!$rl->hit('order:ip:' . $ip, 20, 60)) {
            $this->json(['error' => 'Trop de requetes. Patientez quelques secondes.'], 429);
        }

        $rawBody = file_get_contents('php://input');
        $data    = json_decode($rawBody, true);

        if (!$data || empty($data['qr_token']) || empty($data['items'])) {
            $this->json(['error' => 'Donnees manquantes.'], 400);
        }

        // RATE LIMIT par table (un seul QR ne peut pas spammer)
        // Max 10 commandes par table / minute (raisonnable pour gros groupes)
        if (!$rl->hit('order:token:' . substr($data['qr_token'], 0, 32), 10, 60)) {
            $this->json(['error' => 'Trop de commandes sur cette table. Patientez.'], 429);
        }

        // Trouver la table + le restaurant via le token (recherche globale)
        $tableModel = new Table();
        $table      = $tableModel->findByToken($data['qr_token']);
        if (!$table) {
            $this->json(['error' => 'Table introuvable.'], 404);
        }

        // Bloquer si restaurant suspendu ou abonnement expire
        $expired = $table['abonnement_fin']
                && strtotime($table['abonnement_fin']) < time();
        if ($table['restaurant_statut'] !== 'actif' || $expired) {
            $this->json(['error' => 'Restaurant temporairement indisponible.'], 503);
        }

        $rid = (int) $table['restaurant_id'];

        // Valider et recalculer le total cote serveur (anti-fraude)
        $menuModel = new MenuItem($rid);
        $items     = [];
        $total     = 0.0;

        foreach ($data['items'] as $item) {
            $menuItem = $menuModel->findById((int) $item['id']);
            if (!$menuItem || !$menuItem['disponible']) continue;
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

        // Limiter la quantite max par article (anti-abus)
        foreach ($items as &$it) {
            $it['quantite'] = min($it['quantite'], 50);
        }
        unset($it);

        $notes      = $this->sanitize($data['notes'] ?? '');
        $orderModel = new Order($rid);

        try {
            $orderModel->beginTransaction();

            $commandeId = $orderModel->create((int) $table['id'], $total, $notes);

            $orderItemModel = new OrderItem();
            if (!$orderItemModel->createBulk($commandeId, $items)) {
                throw new \RuntimeException('Echec insertion articles.');
            }

            // Marquer la table comme occupee (variante sans require session)
            $tableModel->updateStatutByRid((int) $table['id'], $rid, 'occupee');

            $orderModel->commit();
        } catch (\Throwable $e) {
            $orderModel->rollback();
            throw $e;
        }

        // Recuperer le numero_local affiche au client
        $newOrder = $orderModel->findByIdGlobal($commandeId);
        $numeroLocal = (int) ($newOrder['numero_local'] ?? 0);

        $this->json([
            'success'      => true,
            'commande_id'  => $commandeId,
            'numero_local' => $numeroLocal,
            'total'        => $total,
        ]);
    }

    /** GET /order/status/{id} - retourne le statut d une commande */
    public function status(string $id): void {
        $commandeId = (int) $id;
        if ($commandeId <= 0) {
            $this->json(['error' => 'ID invalide.'], 400);
        }

        // Lookup global (le client connait son id de commande, pas de leak)
        $orderModel = new Order();
        $commande   = $orderModel->findByIdGlobal($commandeId);

        if (!$commande) {
            $this->json(['error' => 'Commande introuvable.'], 404);
        }

        $this->json([
            'id'           => (int) $commande['id'],
            'numero_local' => (int) ($commande['numero_local'] ?? 0),
            'statut'       => $commande['statut'],
            'label'        => View::statusLabel($commande['statut']),
            'total'        => $commande['total'],
        ]);
    }
}
