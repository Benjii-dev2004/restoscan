<?php
/**
 * app/views/admin/orders.php
 * Vue MVC — back-office : historique des commandes (paginé, filtrable)
 */
$qs = function(array $overrides) use ($filters, $page) {
    $params = array_merge([
        'statut'     => $filters['statut'],
        'date_start' => $filters['date_start'],
        'date_end'   => $filters['date_end'],
        'table_id'   => $filters['table_id'] ?: '',
        'page'       => $page,
    ], $overrides);
    return http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== 0));
};
?>
<div class="page-header">
    <h1 class="page-title"><i class="fa-solid fa-receipt"></i> Commandes</h1>
    <?php
    $exportPreset = 'custom';
    $exportFrom   = $filters['date_start'] ?: date('Y-m-01');
    $exportTo     = $filters['date_end']   ?: date('Y-m-d');
    ?>
    <a href="<?= View::url('admin/reports/export?preset=custom&from=' . urlencode($exportFrom) . '&to=' . urlencode($exportTo)) ?>"
       class="btn btn--sm btn--outline">
        <i class="fa-solid fa-file-csv"></i> Exporter CSV
    </a>
</div>

<!-- Filtres -->
<div class="admin-card filters-bar">
    <form method="GET" action="<?= View::url('admin/orders') ?>" class="filters-form">
        <div class="form-group">
            <label class="form-label">Du</label>
            <input type="date" name="date_start" class="form-input"
                value="<?= View::e($filters['date_start']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Au</label>
            <input type="date" name="date_end" class="form-input"
                value="<?= View::e($filters['date_end']) ?>">
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

<p style="color:#6b7280;font-size:.9rem;margin:.5rem 0 1rem">
    <strong><?= (int) $total ?></strong> commande(s) — Page <?= (int) $page ?> / <?= (int) $pages ?>
</p>

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
            <?php foreach ($orders as $order):
                $numero = (int) ($order['numero_local'] ?? $order['id']);
            ?>
            <tr>
                <td><strong>#<?= $numero ?></strong>
                    <small style="color:#94a3b8;font-size:.7rem">(DB#<?= (int)$order['id'] ?>)</small>
                </td>
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

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
    <a href="?<?= $qs(['page' => 1]) ?>" class="pagination__btn">«</a>
    <a href="?<?= $qs(['page' => $page - 1]) ?>" class="pagination__btn">‹ Préc.</a>
    <?php endif; ?>

    <?php
    $rangeStart = max(1, $page - 2);
    $rangeEnd   = min($pages, $page + 2);
    for ($p = $rangeStart; $p <= $rangeEnd; $p++):
    ?>
    <a href="?<?= $qs(['page' => $p]) ?>"
       class="pagination__btn <?= $p === $page ? 'pagination__btn--active' : '' ?>">
        <?= $p ?>
    </a>
    <?php endfor; ?>

    <?php if ($page < $pages): ?>
    <a href="?<?= $qs(['page' => $page + 1]) ?>" class="pagination__btn">Suiv. ›</a>
    <a href="?<?= $qs(['page' => $pages]) ?>" class="pagination__btn">»</a>
    <?php endif; ?>
</div>

<style>
.pagination {
    display: flex;
    justify-content: center;
    gap: .4rem;
    margin: 1.5rem 0;
    flex-wrap: wrap;
}
.pagination__btn {
    padding: .5rem .85rem;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    color: #374151;
    text-decoration: none;
    font-size: .85rem;
    font-weight: 600;
    transition: all .15s;
}
.pagination__btn:hover { background: #f3f4f6; border-color: #d1d5db; }
.pagination__btn--active {
    background: var(--color-primary, #e85d04);
    color: white;
    border-color: var(--color-primary, #e85d04);
}
</style>
<?php endif; ?>
