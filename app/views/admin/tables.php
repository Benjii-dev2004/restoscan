<?php
/**
 * app/views/admin/tables.php
 * Vue MVC — back-office : gestion des tables et QR codes
 */
?>
<div class="page-header">
    <h1 class="page-title"><i class="fa-solid fa-table-cells"></i> Tables & QR Codes</h1>
    <button class="btn btn--primary" onclick="modalOpen('modalAddTable')">
        <i class="fa-solid fa-plus"></i> Nouvelle table
    </button>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="alert alert--danger" style="margin-bottom:1rem">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <?= View::e($_SESSION['flash_error']) ?>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<?php if (!empty($onLocalhost)): ?>
<div class="alert alert--warning" style="margin-bottom:1.25rem;display:flex;gap:.75rem;align-items:flex-start;">
    <i class="fa-solid fa-triangle-exclamation" style="margin-top:.15rem;flex-shrink:0;"></i>
    <div>
        <?php if ($lanIp): ?>
            <strong>QR codes pour téléphone ✓</strong> —
            Les QR codes seront générés avec l'IP <strong><?= View::e($lanIp) ?></strong>.<br>
            Assurez-vous que le téléphone est sur le <strong>même Wi-Fi</strong> que ce PC,
            puis clique sur <strong>Régénérer tous les QR codes</strong> ci-dessous.
        <?php else: ?>
            <strong>QR codes pour téléphone — IP non configurée</strong><br>
            Rends-toi dans
            <a href="<?= View::url('admin/settings') ?>" style="font-weight:600;">
                Paramètres → IP LAN
            </a>
            et saisis l'adresse IP Wi-Fi de ce PC (ex: <code>192.168.1.135</code>).<br>
            <small>Pour la trouver : ouvre <code>cmd</code> → tape <code>ipconfig</code>
            → cherche <em>"Adresse IPv4"</em> dans la section Wi-Fi.</small>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div style="margin-bottom:1.25rem;text-align:right;">
    <form method="POST" action="<?= View::url('admin/tables/qrcache/clear') ?>"
          onsubmit="return confirm('Vider le cache QR ? Les codes seront régénérés à la prochaine ouverture.')"
          style="display:inline;">
        <?= View::csrfField() ?>
        <button type="submit" class="btn btn--sm btn--outline">
            <i class="fa-solid fa-rotate"></i> Régénérer tous les QR codes
        </button>
    </form>
</div>

<div class="tables-admin-grid">
    <?php foreach ($tables as $t): ?>
    <div class="table-admin-card table-admin-card--<?= View::e($t['statut']) ?>">
        <div class="table-admin-card__number">
            Table <?= (int)$t['numero'] ?>
        </div>
        <div class="table-admin-card__info">
            <span><i class="fa-solid fa-users"></i> <?= (int)$t['capacite'] ?> places</span>
            <span class="table-status-badge table-status-badge--<?= View::e($t['statut']) ?>">
                <?= View::e($t['statut']) ?>
            </span>
        </div>
        <div class="table-admin-card__qr">
            <code><?= View::e(substr($t['qr_token'], 0, 16)) ?>…</code>
        </div>
        <div class="table-admin-card__actions">
            <a
                href="<?= View::url('admin/tables/qrcode/' . $t['id']) ?>"
                class="btn btn--sm btn--primary"
                title="Télécharger le QR code"
            >
                <i class="fa-solid fa-download"></i> QR
            </a>
            <button
                class="btn btn--sm btn--outline"
                title="Imprimer le QR code"
                onclick="printQrCode('<?= View::url('admin/tables/qrcode/' . $t['id']) ?>', <?= (int)$t['numero'] ?>)"
            >
                <i class="fa-solid fa-print"></i>
            </button>
            <a
                href="<?= View::url('menu/' . $t['qr_token']) ?>"
                class="btn btn--sm btn--outline"
                target="_blank"
                title="Voir le menu"
            >
                <i class="fa-solid fa-eye"></i>
            </a>
            <form method="POST" action="<?= View::url('admin/tables/delete/' . $t['id']) ?>"
                onsubmit="return confirm('Supprimer la table <?= (int)$t['numero'] ?> ?')"
                style="display:inline;">
                <?= View::csrfField() ?>
                <button type="submit" class="btn btn--sm btn--danger">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($tables)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-table-cells-large"></i>
        <p>Aucune table. Créez votre première table pour générer un QR code.</p>
    </div>
    <?php endif; ?>
</div>

<script>
function printQrCode(qrUrl, tableNum) {
    const win = window.open('', '_blank', 'width=400,height=500');
    win.document.write(`<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<title>QR Code — Table ${tableNum}</title>
<style>
  body { margin:0; display:flex; flex-direction:column; align-items:center;
         justify-content:center; min-height:100vh; font-family:sans-serif; }
  h2 { margin:0 0 1rem; font-size:1.4rem; }
  img { width:280px; height:280px; }
  p  { margin:.5rem 0 0; color:#555; font-size:.9rem; }
</style>
</head><body>
<h2>Table ${tableNum}</h2>
<img src="${qrUrl}" alt="QR Code Table ${tableNum}" onload="window.print()">
<p>Scannez pour commander</p>
</body></html>`);
    win.document.close();
}
</script>

<!-- Modal : Ajouter une table -->
<div class="modal" id="modalAddTable">
    <div class="modal__backdrop" onclick="modalClose('modalAddTable')"></div>
    <div class="modal__box modal__box--sm">
        <div class="modal__header">
            <h3><i class="fa-solid fa-plus"></i> Nouvelle table</h3>
            <button class="modal__close" onclick="modalClose('modalAddTable')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="<?= View::url('admin/tables/add') ?>">
            <?= View::csrfField() ?>
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Numéro de table *</label>
                    <input type="number" name="numero" class="form-input" required min="1"
                        placeholder="Ex: 5">
                </div>
                <div class="form-group">
                    <label class="form-label">Capacité (places)</label>
                    <input type="number" name="capacite" class="form-input" min="1" max="20"
                        value="4">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--outline" onclick="modalClose('modalAddTable')">Annuler</button>
                <button type="submit" class="btn btn--primary">
                    <i class="fa-solid fa-check"></i> Créer + QR Code
                </button>
            </div>
        </form>
    </div>
</div>
