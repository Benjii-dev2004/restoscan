<?php
/**
 * app/views/superadmin/restaurant_new.php
 */
?>
<h1 class="sa-page-title">
    <a href="<?= View::url('superadmin/dashboard') ?>" style="color:#94a3b8;font-size:1rem"><i class="fa-solid fa-arrow-left"></i></a>
    Nouveau restaurant
</h1>

<?php if (!empty($_SESSION['sa_flash_error'])): ?>
<div class="sa-flash sa-flash--error"><i class="fa-solid fa-circle-exclamation"></i> <?= View::e($_SESSION['sa_flash_error']) ?></div>
<?php unset($_SESSION['sa_flash_error']); endif; ?>

<div class="sa-card">
    <form method="POST" action="<?= View::url('superadmin/restaurant/create') ?>" class="sa-form">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= View::e($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">

        <div class="sa-section-title">Informations restaurant</div>

        <div>
            <label>Nom du restaurant *</label>
            <input type="text" name="nom" required placeholder="Le Petit Bistrot">
        </div>

        <div class="sa-form__row">
            <div>
                <label>Email du gerant</label>
                <input type="email" name="gerant_email" placeholder="contact@bistrot.com">
            </div>
            <div>
                <label>Telephone</label>
                <input type="text" name="gerant_telephone" placeholder="+225 0X XX XX XX XX">
            </div>
        </div>

        <div class="sa-form__row">
            <div>
                <label>Formule *</label>
                <select name="formule">
                    <option value="starter">Starter</option>
                    <option value="pro" selected>Pro</option>
                    <option value="premium">Premium</option>
                </select>
            </div>
            <div>
                <label>Duree d abonnement (mois) *</label>
                <input type="number" name="duree_mois" value="1" min="1" max="60" required>
            </div>
        </div>

        <div class="sa-section-title">Compte administrateur du restaurant</div>

        <div>
            <label>Nom de l admin *</label>
            <input type="text" name="admin_nom" required placeholder="Jean Dupont">
        </div>

        <div class="sa-form__row">
            <div>
                <label>Email de connexion *</label>
                <input type="email" name="admin_email" required placeholder="admin@bistrot.com">
            </div>
            <div>
                <label>Mot de passe (8 caracteres min) *</label>
                <input type="password" name="admin_password" required minlength="8">
            </div>
        </div>

        <div style="display:flex;gap:1rem;margin-top:1rem">
            <button type="submit" class="sa-btn">
                <i class="fa-solid fa-check"></i> Creer le restaurant
            </button>
            <a href="<?= View::url('superadmin/dashboard') ?>" class="sa-btn sa-btn--ghost">Annuler</a>
        </div>
    </form>
</div>
