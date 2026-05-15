<?php
/**
 * app/views/admin/menu.php
 * Vue MVC — back-office : gestion des plats du menu
 */
?>
<div class="page-header">
    <h1 class="page-title"><i class="fa-solid fa-book-open"></i> Menu</h1>
    <button class="btn btn--primary" onclick="modalOpen('modalAddItem')">
        <i class="fa-solid fa-plus"></i> Nouveau plat
    </button>
</div>

<!-- Liste des plats -->
<div class="admin-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Photo</th>
                <th>Nom</th>
                <th>Catégorie</th>
                <th>Prix</th>
                <th>Prépa</th>
                <th>Dispo</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr id="row-<?= (int)$item['id'] ?>" class="<?= $item['disponible'] ? '' : 'row--disabled' ?>">
                <td>
                    <?php if ($item['image']): ?>
                    <img src="<?= View::asset(View::e($item['image'])) ?>" alt="" class="table-img">
                    <?php else: ?>
                    <div class="table-img-placeholder"><i class="fa-solid fa-image"></i></div>
                    <?php endif; ?>
                </td>
                <td><strong><?= View::e($item['nom']) ?></strong>
                    <?php if ($item['description']): ?>
                    <br><small class="text-muted"><?= View::e(mb_substr($item['description'], 0, 60)) ?><?= mb_strlen($item['description']) > 60 ? '…' : '' ?></small>
                    <?php endif; ?>
                </td>
                <td><?= View::e($item['categorie_nom']) ?></td>
                <td><strong><?= View::price((float)$item['prix']) ?></strong></td>
                <td><?= (int)$item['temps_preparation'] ?>min</td>
                <td>
                    <button
                        class="toggle-btn <?= $item['disponible'] ? 'toggle-btn--on' : 'toggle-btn--off' ?>"
                        onclick="toggleAvailability(<?= (int)$item['id'] ?>, this)"
                        title="<?= $item['disponible'] ? 'Marquer épuisé' : 'Rendre disponible' ?>"
                    >
                        <?= $item['disponible'] ? 'Disponible' : 'Épuisé' ?>
                    </button>
                </td>
                <td class="actions-cell">
                    <button class="btn btn--sm btn--outline"
                        onclick="editItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES) ?>)">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <form method="POST" action="<?= View::url('admin/menu/delete/' . $item['id']) ?>"
                        onsubmit="return confirm('Supprimer ce plat ?')" style="display:inline;">
                        <?= View::csrfField() ?>
                        <button type="submit" class="btn btn--sm btn--danger">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
            <tr><td colspan="7" class="text-center text-muted">Aucun plat. Commencez par en ajouter un.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal : Ajouter un plat -->
<div class="modal" id="modalAddItem">
    <div class="modal__backdrop" onclick="modalClose('modalAddItem')"></div>
    <div class="modal__box">
        <div class="modal__header">
            <h3><i class="fa-solid fa-plus"></i> Nouveau plat</h3>
            <button class="modal__close" onclick="modalClose('modalAddItem')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="<?= View::url('admin/menu/add') ?>" enctype="multipart/form-data">
            <?= View::csrfField() ?>
            <?= itemFormFields($categories) ?>
            <div class="modal__footer">
                <button type="button" class="btn btn--outline" onclick="modalClose('modalAddItem')">Annuler</button>
                <button type="submit" class="btn btn--primary">
                    <i class="fa-solid fa-check"></i> Ajouter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal : Modifier un plat -->
<div class="modal" id="modalEditItem">
    <div class="modal__backdrop" onclick="modalClose('modalEditItem')"></div>
    <div class="modal__box">
        <div class="modal__header">
            <h3><i class="fa-solid fa-pen"></i> Modifier le plat</h3>
            <button class="modal__close" onclick="modalClose('modalEditItem')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" id="formEditItem" enctype="multipart/form-data">
            <?= View::csrfField() ?>
            <?= itemFormFields($categories, null, true) ?>
            <div class="modal__footer">
                <button type="button" class="btn btn--outline" onclick="modalClose('modalEditItem')">Annuler</button>
                <button type="submit" class="btn btn--primary">
                    <i class="fa-solid fa-check"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<?php
function itemFormFields(array $categories, ?array $item = null, bool $showDisponible = false): string {
    ob_start();
?>
<div class="form-grid">
    <div class="form-group form-group--full">
        <label class="form-label">Nom du plat *</label>
        <input type="text" name="nom" class="form-input" required
            value="<?= isset($item) ? View::e($item['nom']) : '' ?>"
            placeholder="Ex: Poulet braisé">
    </div>

    <div class="form-group">
        <label class="form-label">Catégorie *</label>
        <select name="categorie_id" class="form-input" required>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>"
                <?= (isset($item) && $item['categorie_id'] == $cat['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['nom']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label class="form-label">Prix (FCFA) *</label>
        <input type="number" name="prix" class="form-input" required min="0" step="50"
            value="<?= isset($item) ? (float)$item['prix'] : '' ?>"
            placeholder="2500">
    </div>

    <div class="form-group">
        <label class="form-label">Temps de préparation (min)</label>
        <input type="number" name="temps_preparation" class="form-input" min="1" max="120"
            value="<?= isset($item) ? (int)$item['temps_preparation'] : 15 ?>">
    </div>

    <div class="form-group form-group--full">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-input" rows="2"
            placeholder="Ingrédients, allergènes…"><?= isset($item) ? htmlspecialchars($item['description']) : '' ?></textarea>
    </div>

    <div class="form-group form-group--full">
        <label class="form-label">Photo du plat</label>
        <input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/webp">
        <small class="form-hint">JPG, PNG ou WebP · max 2 Mo</small>
    </div>

    <?php if ($showDisponible): ?>
    <div class="form-group">
        <label class="form-label">Disponibilité</label>
        <select name="disponible" class="form-input">
            <option value="1">Disponible</option>
            <option value="0">Épuisé</option>
        </select>
    </div>
    <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}
?>

<script>
function editItem(item) {
    const form = document.getElementById('formEditItem');
    form.action = BASE_URL + '/admin/menu/edit/' + item.id;

    // Remplir les champs
    form.nom.value              = item.nom;
    form.categorie_id.value     = item.categorie_id;
    form.prix.value             = item.prix;
    form.temps_preparation.value = item.temps_preparation;
    form.description.value      = item.description || '';
    if (form.disponible) form.disponible.value = item.disponible;

    modalOpen('modalEditItem');
}

function toggleAvailability(id, btn) {
    // SEC-05 : envoyer le token CSRF en header
    fetch(BASE_URL + '/admin/menu/toggle/' + id, {
        method:  'POST',
        headers: { 'X-Csrf-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '' },
    })
        .then(r => r.json())
        .then(() => {
            const isOn = btn.classList.contains('toggle-btn--on');
            btn.classList.toggle('toggle-btn--on', !isOn);
            btn.classList.toggle('toggle-btn--off', isOn);
            btn.textContent = isOn ? 'Épuisé' : 'Disponible';
            const row = document.getElementById('row-' + id);
            if (row) row.classList.toggle('row--disabled', isOn);
        });
}
</script>
