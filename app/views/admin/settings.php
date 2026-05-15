<?php
/**
 * app/views/admin/settings.php
 * Vue MVC — back-office : paramètres du restaurant
 */
?>
<div class="page-header">
    <h1 class="page-title"><i class="fa-solid fa-gear"></i> Paramètres</h1>
</div>

<?php if (!empty($success)): ?>
<div class="alert alert--success">
    <i class="fa-solid fa-check-circle"></i> Paramètres enregistrés avec succès.
</div>
<?php endif; ?>

<div class="admin-card" style="padding: 2rem;">
    <form method="POST" action="<?= View::url('admin/settings/save') ?>" enctype="multipart/form-data">
        <?= View::csrfField() ?>

        <div class="form-grid">
            <div class="form-group form-group--full">
                <label class="form-label">
                    <i class="fa-solid fa-store" style="color:var(--color-primary)"></i>
                    Nom du restaurant *
                </label>
                <input type="text" name="nom_restaurant" class="form-input"
                    value="<?= View::e($settings['nom_restaurant'] ?? 'Mon Restaurant') ?>"
                    placeholder="Ex: Le Palais, Chez Mamie…" required>
            </div>

            <div class="form-group form-group--full">
                <label class="form-label">
                    <i class="fa-solid fa-quote-left" style="color:var(--color-primary)"></i>
                    Slogan / Sous-titre
                </label>
                <input type="text" name="slogan" class="form-input"
                    value="<?= View::e($settings['slogan'] ?? '') ?>"
                    placeholder="Ex: La cuisine du cœur">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fa-solid fa-palette" style="color:var(--color-primary)"></i>
                    Couleur principale
                </label>
                <div style="display:flex;gap:.75rem;align-items:center;">
                    <input type="color" name="couleur_principale" class="form-input"
                        style="width:60px;height:44px;padding:.25rem;cursor:pointer;"
                        value="<?= View::e($settings['couleur_principale'] ?? '#e85d04') ?>">
                    <span style="font-size:.85rem;color:var(--color-muted)">
                        Couleur des boutons et accents de l'interface client
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fa-solid fa-coins" style="color:var(--color-primary)"></i>
                    Devise
                </label>
                <input type="text" name="devise" class="form-input"
                    value="<?= View::e($settings['devise'] ?? 'FCFA') ?>"
                    placeholder="FCFA, EUR, USD…" style="max-width:160px;">
            </div>

            <div class="form-group form-group--full">
                <label class="form-label">
                    <i class="fa-solid fa-network-wired" style="color:var(--color-primary)"></i>
                    IP LAN (accès téléphone en Wi-Fi)
                </label>
                <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
                    <input type="text" name="ip_locale" class="form-input"
                        style="max-width:220px;"
                        value="<?= View::e($settings['ip_locale'] ?? '') ?>"
                        placeholder="Ex: 192.168.1.135">
                    <button type="button" class="btn btn--sm btn--outline" onclick="detectIp()">
                        <i class="fa-solid fa-magnifying-glass"></i> Détecter
                    </button>
                    <span id="ipDetectResult" style="font-size:.85rem;color:var(--color-muted)"></span>
                </div>
                <small class="form-hint">
                    IP locale de ce PC sur le réseau Wi-Fi. Utilisée pour générer des QR codes
                    scannables depuis un téléphone. Trouve-la avec <code>ipconfig</code> (Windows)
                    ou dans les paramètres Wi-Fi de ton PC.
                </small>
            </div>

            <div class="form-group form-group--full">
                <label class="form-label">
                    <i class="fa-solid fa-image" style="color:var(--color-primary)"></i>
                    Logo du restaurant
                </label>
                <?php if (!empty($settings['logo'])): ?>
                <div style="margin-bottom:.75rem;">
                    <img src="<?= View::asset(View::e($settings['logo'])) ?>"
                        alt="Logo" style="height:60px;object-fit:contain;border-radius:.5rem;">
                </div>
                <?php endif; ?>
                <input type="file" name="logo" class="form-input"
                    accept="image/jpeg,image/png,image/webp,image/svg+xml">
                <small class="form-hint">PNG, JPG ou SVG — max 1 Mo. Affiché dans le header du menu client.</small>
            </div>
        </div>

        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn--primary">
                <i class="fa-solid fa-check"></i> Enregistrer les paramètres
            </button>
        </div>
    </form>
</div>

<script>
async function detectIp() {
    const btn    = document.querySelector('[onclick="detectIp()"]');
    const result = document.getElementById('ipDetectResult');
    btn.disabled = true;
    result.textContent = 'Détection…';
    try {
        const res  = await fetch(BASE_URL + '/admin/settings/detect-ip');
        const data = await res.json();
        if (data.ip) {
            document.querySelector('[name="ip_locale"]').value = data.ip;
            result.textContent = '✓ IP détectée : ' + data.ip;
            result.style.color = 'var(--color-success)';
        } else {
            result.textContent = 'Non détecté — saisis-la manuellement.';
        }
    } catch {
        result.textContent = 'Erreur de détection.';
    }
    btn.disabled = false;
}
</script>
