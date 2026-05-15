<?php
/**
 * app/views/admin/categories.php
 * Vue MVC — back-office : gestion des catégories du menu
 */
?>
<div class="page-header">
    <h1 class="page-title"><i class="fa-solid fa-tags"></i> Catégories</h1>
    <button class="btn btn--primary" onclick="modalOpen('modalAddCat')">
        <i class="fa-solid fa-plus"></i> Nouvelle catégorie
    </button>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="alert alert--danger" style="margin-bottom:1rem">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <?= View::e($_SESSION['flash_error']) ?>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<div class="admin-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Icône</th>
                <th>Nom</th>
                <th>Ordre</th>
                <th>Plats dispo</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr>
                <td>
                    <div class="cat-icon-preview">
                        <i class="fa-solid fa-<?= View::e($cat['icone']) ?>"></i>
                    </div>
                </td>
                <td><strong><?= View::e($cat['nom']) ?></strong></td>
                <td><?= (int)$cat['ordre'] ?></td>
                <td>
                    <span class="badge"><?= (int)$cat['nb_plats'] ?> plats</span>
                </td>
                <td>
                    <form method="POST" action="<?= View::url('admin/categories/delete/' . $cat['id']) ?>"
                        onsubmit="return confirm('Supprimer cette catégorie ?')"
                        style="display:inline;">
                        <?= View::csrfField() ?>
                        <button type="submit" class="btn btn--sm btn--danger">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
            <tr><td colspan="5" class="text-center text-muted">Aucune catégorie.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal : Ajouter une catégorie -->
<div class="modal" id="modalAddCat">
    <div class="modal__backdrop" onclick="modalClose('modalAddCat')"></div>
    <div class="modal__box modal__box--sm">
        <div class="modal__header">
            <h3><i class="fa-solid fa-tag"></i> Nouvelle catégorie</h3>
            <button class="modal__close" onclick="modalClose('modalAddCat')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="<?= View::url('admin/categories/add') ?>">
            <?= View::csrfField() ?>
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="nom" class="form-input" required
                        placeholder="Ex: Plats, Entrées, Boissons…">
                </div>
                <div class="form-group">
                    <label class="form-label">Ordre d'affichage</label>
                    <input type="number" name="ordre" class="form-input" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Icône Font Awesome</label>
                    <input type="text" name="icone" class="form-input" value="utensils"
                        placeholder="pizza, salad, wine-glass…">
                    <small class="form-hint">
                        Nom de l'icône sans "fa-" — voir
                        <a href="https://fontawesome.com/icons" target="_blank">fontawesome.com/icons</a>
                    </small>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--outline" onclick="modalClose('modalAddCat')">Annuler</button>
                <button type="submit" class="btn btn--primary">
                    <i class="fa-solid fa-check"></i> Créer
                </button>
            </div>
        </form>
    </div>
</div>
