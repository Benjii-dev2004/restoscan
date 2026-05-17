<?php
/**
 * app/views/superadmin/health.php
 */
$errorTypeColors = [
    'Exception'   => '#ef4444',
    'FatalError'  => '#dc2626',
    'E_ERROR'     => '#ef4444',
    'E_WARNING'   => '#f59e0b',
    'E_NOTICE'    => '#3b82f6',
    'E_DEPRECATED'=> '#6b7280',
];
$colorFor = fn(string $t) => $errorTypeColors[$t] ?? '#6b7280';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h1 class="sa-page-title" style="margin:0">
        <i class="fa-solid fa-heart-pulse"></i> Santé du système
    </h1>
    <a href="<?= View::url('superadmin/dashboard') ?>" class="sa-btn sa-btn--ghost">
        <i class="fa-solid fa-arrow-left"></i> Retour
    </a>
</div>

<!-- Stats principales -->
<div class="sa-stats">
    <div class="sa-stat <?= $h['db_ok'] ? 'sa-stat--green' : 'sa-stat--red' ?>">
        <div class="sa-stat__value">
            <?= $h['db_ok'] ? '✓' : '✗' ?>
        </div>
        <div class="sa-stat__label">Base de données <?= $h['db_ok'] ? 'opérationnelle' : 'INACCESSIBLE' ?></div>
    </div>

    <div class="sa-stat">
        <div class="sa-stat__value"><?= $h['db_size_mb'] ?></div>
        <div class="sa-stat__label">Taille BDD (Mo)</div>
    </div>

    <div class="sa-stat <?= $h['errors_today'] > 10 ? 'sa-stat--red' : ($h['errors_today'] > 0 ? 'sa-stat--yellow' : 'sa-stat--green') ?>">
        <div class="sa-stat__value"><?= (int) $h['errors_today'] ?></div>
        <div class="sa-stat__label">Erreurs aujourd'hui</div>
    </div>

    <div class="sa-stat <?= empty($h['inactive_restos']) ? 'sa-stat--green' : 'sa-stat--yellow' ?>">
        <div class="sa-stat__value"><?= count($h['inactive_restos']) ?></div>
        <div class="sa-stat__label">Restos inactifs (24h)</div>
    </div>
</div>

<div class="sa-card">
    <div class="sa-card__header">
        <h2><i class="fa-solid fa-server"></i> Informations système</h2>
    </div>
    <table class="sa-table">
        <tbody>
            <tr>
                <td>PHP Version</td>
                <td><code style="background:#0f172a;padding:2px 8px;border-radius:4px"><?= View::e($h['php_version']) ?></code></td>
            </tr>
            <tr>
                <td>Dernier cron exécuté</td>
                <td>
                    <?php if ($h['cron_last']): ?>
                        <?= View::e($h['cron_last']) ?>
                        <?php
                        $diff = time() - strtotime($h['cron_last']);
                        $hours = floor($diff / 3600);
                        if ($hours < 26) {
                            echo ' <span style="color:#22c55e">✓ récent</span>';
                        } else {
                            echo ' <span style="color:#f59e0b">⚠ il y a ' . (int)($hours / 24) . ' jour(s)</span>';
                        }
                        ?>
                    <?php else: ?>
                        <span style="color:#ef4444">JAMAIS EXÉCUTÉ</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Logs</td>
                <td><?= (int) $h['disk_logs_kb'] ?> Ko</td>
            </tr>
            <tr>
                <td>QR codes générés</td>
                <td><?= (int) $h['disk_qr_kb'] ?> Ko</td>
            </tr>
            <tr>
                <td>Images uploadées</td>
                <td><?= (int) $h['disk_img_kb'] ?> Ko</td>
            </tr>
            <tr>
                <td>Endpoint /healthz</td>
                <td>
                    <a href="<?= View::url('healthz') ?>" target="_blank" style="color:#f97316">
                        <?= BASE_URL ?>/healthz <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Restaurants inactifs -->
<?php if (!empty($h['inactive_restos'])): ?>
<div class="sa-card">
    <div class="sa-card__header">
        <h2><i class="fa-solid fa-bell"></i> Restaurants sans commande depuis 24h</h2>
    </div>
    <table class="sa-table">
        <thead>
            <tr>
                <th>Restaurant</th>
                <th>Slug</th>
                <th>Dernière commande</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($h['inactive_restos'] as $r): ?>
            <tr>
                <td><?= View::e($r['nom']) ?></td>
                <td><code><?= View::e($r['slug']) ?></code></td>
                <td>
                    <?= $r['last_order']
                        ? date('d/m/Y H:i', strtotime($r['last_order']))
                        : '<span style="color:#ef4444">Aucune commande jamais</span>' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Erreurs récentes -->
<div class="sa-card">
    <div class="sa-card__header">
        <h2><i class="fa-solid fa-triangle-exclamation"></i> Erreurs récentes (20 dernières)</h2>
    </div>
    <?php if (empty($h['errors_recent'])): ?>
    <p style="color:#94a3b8;text-align:center;padding:2rem">
        <i class="fa-solid fa-circle-check" style="color:#22c55e;font-size:1.5rem"></i><br>
        Aucune erreur enregistrée.
    </p>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table class="sa-table" style="font-size:.85rem">
        <thead>
            <tr>
                <th>Quand</th>
                <th>Type</th>
                <th>Message</th>
                <th>Fichier</th>
                <th>URL</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($h['errors_recent'] as $e): ?>
            <tr>
                <td style="white-space:nowrap;font-family:monospace">
                    <?= date('d/m H:i:s', strtotime($e['timestamp'] ?? 'now')) ?>
                </td>
                <td>
                    <span class="sa-badge" style="background:<?= $colorFor($e['type'] ?? '') ?>15;color:<?= $colorFor($e['type'] ?? '') ?>">
                        <?= View::e($e['type'] ?? '?') ?>
                    </span>
                </td>
                <td style="max-width:400px;word-break:break-word"><?= View::e($e['message'] ?? '') ?></td>
                <td style="font-family:monospace;font-size:.75rem;color:#94a3b8">
                    <?php if (!empty($e['file'])):
                        $file = basename($e['file']);
                    ?>
                        <?= View::e($file) ?>:<?= (int)($e['line'] ?? 0) ?>
                    <?php endif; ?>
                </td>
                <td style="font-family:monospace;font-size:.75rem;color:#94a3b8;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= View::e($e['url'] ?? '') ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
