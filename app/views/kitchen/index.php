<?php
/**
 * app/views/kitchen/index.php
 * Vue MVC — interface cuisine : tableau Kanban des commandes
 */

function renderTicket(array $order, string $nextStatut, string $nextLabel, string $btnClass): string {
    $elapsed = (int) $order['minutes_elapsed'];
    $urgency = $elapsed >= 20 ? 'ticket--urgent' : ($elapsed >= 10 ? 'ticket--warning' : '');
    $numero  = (int) ($order['numero_local'] ?? $order['id']);

    ob_start();
?>
<div class="ticket <?= $urgency ?>" id="ticket-<?= (int)$order['id'] ?>">
    <div class="ticket__header">
        <span class="ticket__table">
            <strong>#<?= $numero ?></strong>
            <i class="fa-solid fa-table-cells"></i> T<?= (int)$order['table_numero'] ?>
        </span>
        <span class="ticket__time">
            <i class="fa-regular fa-clock"></i>
            <span class="ticket-timer" data-start="<?= (int)($order['created_ts_ms'] ?? strtotime($order['created_at']) * 1000) ?>">
                <?= $elapsed ?>min
            </span>
        </span>
    </div>

    <ul class="ticket__items">
        <?php foreach ($order['items'] as $item): ?>
        <li class="ticket__item">
            <span class="ticket__qty"><?= (int)$item['quantite'] ?>×</span>
            <span class="ticket__name"><?= htmlspecialchars($item['nom']) ?></span>
            <?php if ($item['notes']): ?>
            <span class="ticket__note"><?= htmlspecialchars($item['notes']) ?></span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($order['notes']): ?>
    <div class="ticket__order-note">
        <i class="fa-solid fa-note-sticky"></i>
        <?= htmlspecialchars($order['notes']) ?>
    </div>
    <?php endif; ?>

    <button
        class="ticket__btn <?= $btnClass ?>"
        onclick="kitchenUpdate(<?= (int)$order['id'] ?>, '<?= $nextStatut ?>', this)"
    >
        <?= $nextLabel ?>
        <i class="fa-solid fa-arrow-right"></i>
    </button>
</div>
<?php
    return ob_get_clean();
}
?>

<div class="kanban" id="kanban">

    <!-- Colonne En attente -->
    <div class="kanban__col" id="col-en_attente">
        <div class="kanban__col-header kanban__col-header--waiting">
            <i class="fa-solid fa-hourglass-half"></i>
            <span>En attente</span>
            <span class="kanban__count" id="count-en_attente"><?= count($enAttente) ?></span>
        </div>
        <div class="kanban__cards" id="cards-en_attente">
            <?php foreach ($enAttente as $order): ?>
                <?= renderTicket($order, 'en_preparation', 'Accepter', 'ticket__btn--accept') ?>
            <?php endforeach; ?>
            <?php if (empty($enAttente)): ?>
            <div class="kanban__empty">
                <i class="fa-regular fa-face-smile"></i>
                <span>Aucune commande</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Colonne En préparation -->
    <div class="kanban__col" id="col-en_preparation">
        <div class="kanban__col-header kanban__col-header--preparing">
            <i class="fa-solid fa-fire-burner"></i>
            <span>En préparation</span>
            <span class="kanban__count" id="count-en_preparation"><?= count($enPreparation) ?></span>
        </div>
        <div class="kanban__cards" id="cards-en_preparation">
            <?php foreach ($enPreparation as $order): ?>
                <?= renderTicket($order, 'pret', 'Prêt !', 'ticket__btn--ready') ?>
            <?php endforeach; ?>
            <?php if (empty($enPreparation)): ?>
            <div class="kanban__empty" id="empty-en_preparation">
                <i class="fa-solid fa-fire-burner"></i>
                <span>Rien en cours</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Colonne Prêt -->
    <div class="kanban__col" id="col-pret">
        <div class="kanban__col-header kanban__col-header--ready">
            <i class="fa-solid fa-bell-concierge"></i>
            <span>Prêt à servir</span>
            <span class="kanban__count" id="count-pret">0</span>
        </div>
        <div class="kanban__cards" id="cards-pret">
            <div class="kanban__empty" id="empty-pret">
                <i class="fa-solid fa-check"></i>
                <span>Tout servi</span>
            </div>
        </div>
    </div>

</div>

<!-- Notification sonore (nouvelle commande) -->
<audio id="newOrderSound" preload="auto">
    <source src="<?= View::asset('audio/ding.mp3') ?>" type="audio/mpeg">
</audio>

<!-- Toast notification -->
<div class="kitchen-toast" id="kitchenToast"></div>
