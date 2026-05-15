<?php
/**
 * app/views/admin/orders.php
 * Vue MVC — back-office : historique des commandes
 */
?>
<div class="page-header">
    <h1 class="page-title"><i class="fa-solid fa-receipt"></i> Commandes</h1>
</div>

<!-- Filtres -->
<div class="admin-card filters-bar">
    <form method="GET" action="<?= View::url('admin/orders') ?>" class="filters-form">
        <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-input"
                value="<?= View::e($filters['date']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Statut</label>
            <select name="statut" class="form-input">
                <option value="">Tous</option>
                <?php foreach (['en_attente','en_preparation','pret','servi','annule'] as $s): ?>
                <option value="<?= $s ?>" <?= $filters['statut'] === $s ? 'selected' : '' ?>>
                    <?= View::statusLabel($s) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Table</label>
            <select name="table_id" class="form-input">
                <option value="">Toutes</option>
                <?php foreach ($tables as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $filters['table_id'] == $t['id'] ? 'selected' : '' ?>>
                    Table <?= (int)$t['numero'] ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn--primary">
            <i class="fa-solid fa-filter"></i> Filtrer
        </button>
        <a href="<?= View::url('admin/orders') ?>" class="btn btn--outline">
            <i class="fa-solid fa-xmark"></i> Reset
        </a>
    </form>
</div>

<!-- Tableau des commandes -->
<div class="admin-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Table</th>
                <th>Total</th>
                <th>Statut</th>
                <th>Notes</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
                <td><strong>#<?= (int)$order['id'] ?></strong></td>
                <td>Table <?= (int)$order['table_numero'] ?></td>
                <td><strong><?= View::price((float)$order['total']) ?></strong></td>
                <td>
                    <span class="status-badge <?= View::statusClass($order['statut']) ?>">
                        <?= View::statusLabel($order['statut']) ?>
                    </span>
                </td>
                <td><?= $order['notes'] ? View::e(mb_substr($order['notes'], 0, 40)) : '—' ?></td>
                <td><?= View::date($order['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
            <tr>
                <td colspan="6" class="text-center text-muted">Aucune commande trouvée.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
