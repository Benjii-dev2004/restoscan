<?php
/**
 * app/views/admin/dashboard.php
 * Vue MVC — back-office : tableau de bord statistiques
 */
?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fa-solid fa-chart-line"></i> Dashboard
    </h1>
    <span class="page-date"><?= date('d/m/Y') ?></span>
</div>

<!-- Cartes statistiques -->
<div class="stats-grid">
    <div class="stat-card stat-card--orange">
        <div class="stat-card__icon"><i class="fa-solid fa-receipt"></i></div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int)$stats['nb_commandes'] ?></div>
            <div class="stat-card__label">Commandes aujourd'hui</div>
        </div>
    </div>

    <div class="stat-card stat-card--green">
        <div class="stat-card__icon"><i class="fa-solid fa-coins"></i></div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= View::price((float)$stats['ca_total']) ?></div>
            <div class="stat-card__label">CA du jour</div>
        </div>
    </div>

    <div class="stat-card stat-card--yellow">
        <div class="stat-card__icon"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int)$stats['en_attente'] ?></div>
            <div class="stat-card__label">En attente</div>
        </div>
    </div>

    <div class="stat-card stat-card--blue">
        <div class="stat-card__icon"><i class="fa-solid fa-fire-burner"></i></div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int)$stats['en_preparation'] ?></div>
            <div class="stat-card__label">En préparation</div>
        </div>
    </div>
</div>

<div class="dashboard-cols">

    <!-- Statut des tables -->
    <div class="dashboard-card">
        <div class="dashboard-card__header">
            <h2><i class="fa-solid fa-table-cells"></i> Tables</h2>
            <a href="<?= View::url('admin/tables') ?>" class="btn btn--sm btn--outline">Gérer</a>
        </div>
        <div class="tables-grid">
            <?php foreach ($tables as $t): ?>
            <div class="table-badge table-badge--<?= View::e($t['statut']) ?>">
                <span class="table-badge__num"><?= (int)$t['numero'] ?></span>
                <span class="table-badge__status"><?= View::e($t['statut']) ?></span>
                <?php if ($t['commandes_actives'] > 0): ?>
                <span class="table-badge__orders"><?= (int)$t['commandes_actives'] ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($tables)): ?>
            <p class="text-muted">Aucune table créée.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top 3 plats les plus commandés -->
    <div class="dashboard-card">
        <div class="dashboard-card__header">
            <h2><i class="fa-solid fa-trophy"></i> Top 3 plats</h2>
            <a href="<?= View::url('admin/menu') ?>" class="btn btn--sm btn--outline">Menu</a>
        </div>
        <?php if (empty($topItems)): ?>
        <div class="top-empty">
            <i class="fa-solid fa-bowl-food"></i>
            <p>Aucune commande pour l'instant.</p>
        </div>
        <?php else:
            $medals = ['🥇','🥈','🥉'];
            $maxQte = max(array_column($topItems, 'total_qte')) ?: 1;
        ?>
        <ol class="top-list">
            <?php foreach ($topItems as $i => $item):
                $pct = round((int)$item['total_qte'] / $maxQte * 100);
            ?>
            <li class="top-list__item">
                <span class="top-list__medal"><?= $medals[$i] ?? ($i + 1) ?></span>
                <div class="top-list__info">
                    <span class="top-list__name"><?= View::e($item['nom']) ?></span>
                    <div class="top-list__bar">
                        <div class="top-list__bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <span class="top-list__count"><?= (int)$item['total_qte'] ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </div>

</div>
