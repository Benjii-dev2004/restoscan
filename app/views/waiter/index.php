<?php
/**
 * app/views/waiter/index.php
 * Vue MVC — interface serveur en salle
 * Rôle : afficher les commandes prêtes à servir et en cours
 */
?>

<div class="waiter-page">

    <!-- Section principale : À servir -->
    <section class="waiter-section">
        <div class="waiter-section__header">
            <h2 class="waiter-section__title waiter-section__title--ready">
                <i class="fa-solid fa-bell-concierge"></i>
                Prêt à servir
                <span class="waiter-badge waiter-badge--ready" id="countPret">
                    <?= count($pret) ?>
                </span>
            </h2>
        </div>

        <div class="waiter-cards" id="cardsPret">
            <?php if (empty($pret)): ?>
            <div class="waiter-empty" id="emptyPret">
                <i class="fa-solid fa-check-circle"></i>
                <p>Tout est servi — bien joué !</p>
            </div>
            <?php else: ?>
                <?php foreach ($pret as $order): ?>
                <?= waiterCard($order) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section secondaire : En préparation -->
    <section class="waiter-section waiter-section--secondary">
        <div class="waiter-section__header">
            <h2 class="waiter-section__title waiter-section__title--preparing">
                <i class="fa-solid fa-fire-burner"></i>
                En préparation
                <span class="waiter-badge waiter-badge--preparing" id="countEncours">
                    <?= count($encours) ?>
                </span>
            </h2>
        </div>

        <div class="waiter-cards waiter-cards--small" id="cardsEncours">
            <?php if (empty($encours)): ?>
            <div class="waiter-empty waiter-empty--sm" id="emptyEncours">
                <i class="fa-solid fa-fire-burner"></i>
                <p>Rien en cours</p>
            </div>
            <?php else: ?>
                <?php foreach ($encours as $order): ?>
                <?= waiterCardSmall($order) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

</div>

<!-- Toast notification -->
<div class="waiter-toasts" id="waiterToasts"></div>

<?php

function waiterCard(array $order): string {
    $items = $order['items'] ?? [];
    ob_start();
?>
<?php $numero = (int) ($order['numero_local'] ?? $order['id']); ?>
<div class="waiter-card" id="wcard-<?= (int)$order['id'] ?>">
    <div class="waiter-card__header">
        <div class="waiter-card__table">
            <strong>#<?= $numero ?></strong> ·
            <i class="fa-solid fa-table-cells"></i>
            Table <?= (int)$order['table_numero'] ?>
        </div>
        <div class="waiter-card__time">
            <i class="fa-regular fa-clock"></i>
            <?= View::date($order['created_at'], 'H:i') ?>
        </div>
    </div>

    <ul class="waiter-card__items">
        <?php foreach ($items as $item): ?>
        <li class="waiter-card__item">
            <span class="waiter-card__qty"><?= (int)$item['quantite'] ?>×</span>
            <span class="waiter-card__name"><?= View::e($item['nom']) ?></span>
            <?php if (!empty($item['notes'])): ?>
            <span class="waiter-card__note"><?= View::e($item['notes']) ?></span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!empty($order['notes'])): ?>
    <div class="waiter-card__order-note">
        <i class="fa-solid fa-note-sticky"></i>
        <?= View::e($order['notes']) ?>
    </div>
    <?php endif; ?>

    <div class="waiter-card__total">
        Total : <strong><?= View::price((float)$order['total']) ?></strong>
    </div>

    <button
        class="waiter-card__btn"
        onclick="serveOrder(<?= (int)$order['id'] ?>, this)"
    >
        <i class="fa-solid fa-check"></i>
        Servi — Table <?= (int)$order['table_numero'] ?>
    </button>
</div>
<?php
    return ob_get_clean();
}

function waiterCardSmall(array $order): string {
    $items = $order['items'] ?? [];
    ob_start();
?>
<?php $numero = (int) ($order['numero_local'] ?? $order['id']); ?>
<div class="waiter-card-sm" id="wcard-sm-<?= (int)$order['id'] ?>">
    <div class="waiter-card-sm__table">
        <strong>#<?= $numero ?></strong> ·
        <i class="fa-solid fa-table-cells"></i>
        T<?= (int)$order['table_numero'] ?>
    </div>
    <div class="waiter-card-sm__items">
        <?= implode(', ', array_map(fn($i) => $i['quantite'].'× '.View::e($i['nom']), $items)) ?>
    </div>
    <div class="waiter-card-sm__time">
        <i class="fa-regular fa-clock"></i>
        <?= View::date($order['created_at'], 'H:i') ?>
    </div>
</div>
<?php
    return ob_get_clean();
}
?>
