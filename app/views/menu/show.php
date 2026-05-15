<?php
/**
 * app/views/menu/show.php
 * Vue MVC — interface client : menu interactif (mobile-first)
 */
?>
<!-- Header fixe -->
<header class="client-header">
    <div class="client-header__brand">
        <i class="fa-solid fa-utensils"></i>
        <span><?= View::e($app_name) ?></span>
    </div>
    <div class="client-header__table">
        <i class="fa-solid fa-table-cells"></i>
        Table <?= View::e($table['numero']) ?>
    </div>
</header>

<!-- Navigation catégories (sticky, scroll horizontal) -->
<nav class="category-nav" id="categoryNav">
    <div class="category-nav__inner">
        <?php foreach ($categories as $cat): ?>
        <button
            class="category-nav__btn"
            data-target="cat-<?= (int)$cat['id'] ?>"
            onclick="scrollToCategory('cat-<?= (int)$cat['id'] ?>')"
        >
            <i class="fa-solid fa-<?= View::e($cat['icone']) ?>"></i>
            <?= View::e($cat['nom']) ?>
        </button>
        <?php endforeach; ?>
    </div>
</nav>

<!-- Menu principal -->
<main class="client-main" id="clientMain">
    <?php if (empty($menuByCategory)): ?>
    <div class="menu-empty">
        <i class="fa-solid fa-bowl-food"></i>
        <p>Le menu est en cours de mise à jour.</p>
    </div>
    <?php else: ?>
        <?php foreach ($menuByCategory as $categoryName => $items): ?>
        <?php
            // Trouver l'ID de la catégorie
            $catId = $items[0]['categorie_id'] ?? 0;
            $catIcon = $items[0]['categorie_icone'] ?? 'utensils';
        ?>
        <section class="menu-section" id="cat-<?= (int)$catId ?>">
            <h2 class="menu-section__title">
                <i class="fa-solid fa-<?= View::e($catIcon) ?>"></i>
                <?= View::e($categoryName) ?>
            </h2>

            <div class="menu-grid">
                <?php foreach ($items as $item): ?>
                <article class="menu-card" data-id="<?= (int)$item['id'] ?>">
                    <div class="menu-card__img-wrap">
                        <?php if ($item['image']): ?>
                        <img
                            src="<?= View::asset(View::e($item['image'])) ?>"
                            alt="<?= View::e($item['nom']) ?>"
                            class="menu-card__img"
                            loading="lazy"
                        >
                        <?php else: ?>
                        <div class="menu-card__img-placeholder">
                            <i class="fa-solid fa-<?= View::e($catIcon) ?>"></i>
                        </div>
                        <?php endif; ?>
                        <?php if ($item['temps_preparation']): ?>
                        <span class="menu-card__time">
                            <i class="fa-regular fa-clock"></i> <?= (int)$item['temps_preparation'] ?>min
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="menu-card__body">
                        <h3 class="menu-card__name"><?= View::e($item['nom']) ?></h3>
                        <?php if ($item['description']): ?>
                        <p class="menu-card__desc"><?= View::e($item['description']) ?></p>
                        <?php endif; ?>
                        <div class="menu-card__footer">
                            <span class="menu-card__price"><?= View::price((float)$item['prix']) ?></span>
                            <button
                                class="btn-add"
                                onclick="cartAdd(<?= (int)$item['id'] ?>, '<?= View::e(addslashes($item['nom'])) ?>', <?= (float)$item['prix'] ?>)"
                                aria-label="Ajouter <?= View::e($item['nom']) ?>"
                            >
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Espace bas pour le badge panier flottant -->
    <div style="height: 6rem;"></div>
</main>

<!-- Badge panier flottant -->
<button class="cart-fab" id="cartFab" onclick="cartOpen()" aria-label="Voir le panier" style="display:none;">
    <i class="fa-solid fa-basket-shopping"></i>
    <span class="cart-fab__count" id="cartCount">0</span>
    <span class="cart-fab__total" id="cartTotal">0 FCFA</span>
</button>

<!-- Drawer panier (bottom sheet) -->
<div class="cart-overlay" id="cartOverlay" onclick="cartClose()"></div>
<div class="cart-drawer" id="cartDrawer">
    <div class="cart-drawer__handle"></div>
    <div class="cart-drawer__header">
        <h3><i class="fa-solid fa-basket-shopping"></i> Mon panier</h3>
        <button class="cart-drawer__close" onclick="cartClose()">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div class="cart-drawer__body" id="cartItems">
        <!-- Rempli dynamiquement par cart.js -->
    </div>

    <div class="cart-drawer__footer">
        <div class="cart-summary">
            <div class="cart-summary__notes">
                <label for="orderNotes">
                    <i class="fa-solid fa-note-sticky"></i> Note pour la cuisine
                </label>
                <textarea id="orderNotes" placeholder="Allergies, préférences…" rows="2"></textarea>
            </div>
            <div class="cart-summary__total">
                <span>Total</span>
                <strong id="cartTotalDrawer">0 FCFA</strong>
            </div>
        </div>
        <button class="btn btn--primary btn--full" id="btnOrder" onclick="cartSubmit()">
            <i class="fa-solid fa-paper-plane"></i>
            Commander
        </button>
    </div>
</div>

<!-- Page de confirmation / suivi (masquée par défaut) -->
<div class="order-tracker" id="orderTracker" style="display:none;">
    <div class="order-tracker__inner">
        <div class="order-tracker__icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <h2>Commande envoyée !</h2>
        <p>Table <?= View::e($table['numero']) ?> · <span id="trackerTotal"></span></p>

        <div class="tracker-steps">
            <div class="tracker-step" id="step-en_attente">
                <div class="tracker-step__dot"></div>
                <span>Reçue</span>
            </div>
            <div class="tracker-step" id="step-en_preparation">
                <div class="tracker-step__dot"></div>
                <span>En préparation</span>
            </div>
            <div class="tracker-step" id="step-pret">
                <div class="tracker-step__dot"></div>
                <span>Prête</span>
            </div>
            <div class="tracker-step" id="step-servi">
                <div class="tracker-step__dot"></div>
                <span>Servie</span>
            </div>
        </div>

        <div class="tracker-status-label" id="trackerStatusLabel">En attente…</div>

        <button class="btn btn--outline" onclick="newOrder()">
            <i class="fa-solid fa-plus"></i>
            Ajouter une commande
        </button>
    </div>
</div>

<script>
    // Données de la table injectées pour cart.js
    const TABLE_QR_TOKEN = '<?= View::e($table['qr_token']) ?>';
    const TABLE_NUMBER   = <?= (int)$table['numero'] ?>;
</script>
