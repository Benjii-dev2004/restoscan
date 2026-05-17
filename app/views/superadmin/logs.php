<?php
/**
 * app/views/superadmin/logs.php
 */
$actionLabels = [
    'create'      => ['🆕', 'Création'],
    'extend'      => ['⏰', 'Prolongation'],
    'suspend'     => ['⏸️', 'Suspension'],
    'activate'    => ['▶️', 'Activation'],
    'delete'      => ['🗑️', 'Suppression'],
    'impersonate' => ['👤', 'Impersonation'],
];
?>
<h1 class="sa-page-title">
    <a href="<?= View::url('superadmin/dashboard') ?>" style="color:#94a3b8;font-size:1rem"><i class="fa-solid fa-arrow-left"></i></a>
    <i class="fa-solid fa-clock-rotate-left"></i> Journal des actions
</h1>

<div class="sa-card">
    <div class="sa-card__header">
        <h2>200 dernieres actions</h2>
    </div>

    <?php if (empty($logs)): ?>
    <p style="color:#94a3b8;text-align:center;padding:2rem">Aucune action enregistree.</p>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table class="sa-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Super-admin</th>
                <th>Action</th>
                <th>Restaurant</th>
                <th>Details</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $l):
            [$icon, $label] = $actionLabels[$l['action']] ?? ['•', $l['action']];
        ?>
            <tr>
                <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></td>
                <td><?= View::e($l['sa_nom'] ?? 'inconnu') ?></td>
                <td><?= $icon ?> <?= View::e($label) ?></td>
                <td><?= $l['resto_nom'] ? View::e($l['resto_nom']) : '<span style="color:#64748b">—</span>' ?></td>
                <td style="font-size:.85rem;color:#cbd5e1"><?= View::e($l['details']) ?></td>
                <td style="font-family:monospace;font-size:.8rem;color:#64748b"><?= View::e($l['ip'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
