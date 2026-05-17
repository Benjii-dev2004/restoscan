<?php
/**
 * app/views/superadmin/dashboard.php
 */
$csrf = $_SESSION[CSRF_TOKEN_NAME] ?? '';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h1 class="sa-page-title" style="margin:0"><i class="fa-solid fa-chart-pie"></i> Vue d ensemble</h1>
    <a href="<?= View::url('superadmin/logs') ?>" class="sa-btn sa-btn--ghost">
        <i class="fa-solid fa-clock-rotate-left"></i> Journal
    </a>
</div>

<?php if (!empty($_SESSION['sa_flash_success'])): ?>
<div class="sa-flash sa-flash--success"><i class="fa-solid fa-check"></i> <?= View::e($_SESSION['sa_flash_success']) ?></div>
<?php unset($_SESSION['sa_flash_success']); endif; ?>

<?php if (!empty($_SESSION['sa_flash_error'])): ?>
<div class="sa-flash sa-flash--error"><i class="fa-solid fa-circle-exclamation"></i> <?= View::e($_SESSION['sa_flash_error']) ?></div>
<?php unset($_SESSION['sa_flash_error']); endif; ?>

<div class="sa-stats">
    <div class="sa-stat">
        <div class="sa-stat__value"><?= (int) ($stats['nb_restos'] ?? 0) ?></div>
        <div class="sa-stat__label">Restaurants total</div>
    </div>
    <div class="sa-stat sa-stat--green">
        <div class="sa-stat__value"><?= (int) ($stats['nb_actifs'] ?? 0) ?></div>
        <div class="sa-stat__label">Actifs</div>
    </div>
    <div class="sa-stat sa-stat--yellow">
        <div class="sa-stat__value"><?= (int) ($stats['nb_suspendus'] ?? 0) ?></div>
        <div class="sa-stat__label">Suspendus</div>
    </div>
    <div class="sa-stat sa-stat--red">
        <div class="sa-stat__value"><?= (int) ($stats['nb_expires'] ?? 0) ?></div>
        <div class="sa-stat__label">Expires</div>
    </div>
    <div class="sa-stat sa-stat--yellow">
        <div class="sa-stat__value"><?= (int) ($stats['expirent_bientot'] ?? 0) ?></div>
        <div class="sa-stat__label">Expirent &lt; 30j</div>
    </div>
</div>

<div class="sa-card">
    <div class="sa-card__header">
        <h2><i class="fa-solid fa-store"></i> Restaurants</h2>
        <a href="<?= View::url('superadmin/restaurant/new') ?>" class="sa-btn">
            <i class="fa-solid fa-plus"></i> Nouveau restaurant
        </a>
    </div>

    <?php if (empty($restaurants)): ?>
    <p style="color:#94a3b8;text-align:center;padding:2rem">Aucun restaurant. Cree le premier.</p>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table class="sa-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Nom</th>
                <th>Formule</th>
                <th>Statut</th>
                <th>Abonnement fin</th>
                <th>Stats</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($restaurants as $r):
            $expired = $r['abonnement_fin'] && strtotime($r['abonnement_fin']) < time();
            $daysLeft = $r['abonnement_fin'] ? (int) floor((strtotime($r['abonnement_fin']) - time()) / 86400) : null;
        ?>
            <tr>
                <td><?= (int) $r['id'] ?></td>
                <td>
                    <strong><?= View::e($r['nom']) ?></strong><br>
                    <?php $loginUrl = BASE_URL . '/r/' . $r['slug'] . '/auth/login'; ?>
                    <small style="color:#94a3b8;font-family:monospace;font-size:.7rem">
                        <a href="<?= View::e($loginUrl) ?>" target="_blank" style="color:#f97316">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> /r/<?= View::e($r['slug']) ?>
                        </a>
                    </small>
                    <?php if (!empty($r['gerant_email'])): ?>
                    <br><small style="color:#94a3b8"><i class="fa-solid fa-envelope"></i> <?= View::e($r['gerant_email']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= View::e($r['formule']) ?></td>
                <td>
                    <span class="sa-badge sa-badge--<?= View::e($r['statut']) ?>"><?= View::e($r['statut']) ?></span>
                </td>
                <td>
                    <?php if ($r['abonnement_fin']): ?>
                    <?= date('d/m/Y', strtotime($r['abonnement_fin'])) ?><br>
                    <small style="color:<?= $expired ? '#ef4444' : ($daysLeft < 30 ? '#eab308' : '#94a3b8') ?>">
                        <?= $expired ? 'Expire' : ($daysLeft . ' jour(s) restant') ?>
                    </small>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td style="font-size:.85rem;color:#94a3b8">
                    <?= (int) ($r['nb_tables'] ?? 0) ?> tables<br>
                    <?= (int) ($r['nb_users'] ?? 0) ?> users<br>
                    <?= (int) ($r['cmd_aujourdhui'] ?? 0) ?> cmd auj.
                </td>
                <td>
                    <div class="sa-actions-inline">
                        <form method="POST" action="<?= View::url('superadmin/restaurant/impersonate/' . $r['id']) ?>" class="sa-inline-form" onsubmit="return confirm('Se connecter en tant que admin de ce restaurant ?')">
                            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= View::e($csrf) ?>">
                            <button type="submit" class="sa-btn sa-btn--sm" title="Mode SAV : se connecter comme l admin">
                                <i class="fa-solid fa-user-secret"></i>
                            </button>
                        </form>
                        <form method="POST" action="<?= View::url('superadmin/restaurant/extend/' . $r['id']) ?>" class="sa-inline-form" onsubmit="return confirm('Prolonger de combien de mois ?')">
                            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= View::e($csrf) ?>">
                            <input type="number" name="months" value="1" min="1" max="60" style="width:60px;padding:.3rem">
                            <button type="submit" class="sa-btn sa-btn--sm sa-btn--ghost"><i class="fa-solid fa-clock"></i> +mois</button>
                        </form>
                        <form method="POST" action="<?= View::url('superadmin/restaurant/toggle/' . $r['id']) ?>" class="sa-inline-form">
                            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= View::e($csrf) ?>">
                            <button type="submit" class="sa-btn sa-btn--sm sa-btn--yellow">
                                <i class="fa-solid fa-<?= $r['statut'] === 'actif' ? 'pause' : 'play' ?>"></i>
                                <?= $r['statut'] === 'actif' ? 'Suspendre' : 'Activer' ?>
                            </button>
                        </form>
                        <form method="POST" action="<?= View::url('superadmin/restaurant/delete/' . $r['id']) ?>" class="sa-inline-form" onsubmit="return confirm('Supprimer DEFINITIVEMENT ce restaurant et toutes ses donnees ?')">
                            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= View::e($csrf) ?>">
                            <button type="submit" class="sa-btn sa-btn--sm sa-btn--red"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
