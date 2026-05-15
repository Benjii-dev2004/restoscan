<?php
/**
 * app/views/admin/users.php
 * Vue MVC — back-office : gestion des utilisateurs
 */
?>
<div class="page-header">
    <h1 class="page-title"><i class="fa-solid fa-users"></i> Utilisateurs</h1>
    <button class="btn btn--primary" onclick="modalOpen('modalAddUser')">
        <i class="fa-solid fa-plus"></i> Nouvel utilisateur
    </button>
</div>

<div class="admin-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Email</th>
                <th>Rôle</th>
                <th>Créé le</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div class="user-avatar-inline">
                        <span class="avatar-sm"><?= strtoupper(substr($u['nom'], 0, 1)) ?></span>
                        <strong><?= View::e($u['nom']) ?></strong>
                    </div>
                </td>
                <td><?= View::e($u['email']) ?></td>
                <td>
                    <span class="role-badge role-badge--<?= View::e($u['role']) ?>">
                        <?= View::e($u['role']) ?>
                    </span>
                </td>
                <td><?= View::date($u['created_at'], 'd/m/Y') ?></td>
                <td>
                    <?php if ($u['id'] != ($_SESSION['user']['id'] ?? 0)): ?>
                    <form method="POST" action="<?= View::url('admin/users/delete/' . $u['id']) ?>"
                        onsubmit="return confirm('Supprimer cet utilisateur ?')"
                        style="display:inline;">
                        <?= View::csrfField() ?>
                        <button type="submit" class="btn btn--sm btn--danger">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="text-muted">Vous</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal : Ajouter un utilisateur -->
<div class="modal" id="modalAddUser">
    <div class="modal__backdrop" onclick="modalClose('modalAddUser')"></div>
    <div class="modal__box modal__box--sm">
        <div class="modal__header">
            <h3><i class="fa-solid fa-user-plus"></i> Nouvel utilisateur</h3>
            <button class="modal__close" onclick="modalClose('modalAddUser')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="<?= View::url('admin/users/add') ?>">
            <?= View::csrfField() ?>
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Nom complet *</label>
                    <input type="text" name="nom" class="form-input" required placeholder="Jean Dupont">
                </div>
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-input" required placeholder="jean@restaurant.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Mot de passe *</label>
                    <input type="password" name="password" class="form-input" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">Rôle *</label>
                    <select name="role" class="form-input" required>
                        <option value="cuisine">Cuisine</option>
                        <option value="serveur">Serveur</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--outline" onclick="modalClose('modalAddUser')">Annuler</button>
                <button type="submit" class="btn btn--primary">
                    <i class="fa-solid fa-check"></i> Créer
                </button>
            </div>
        </form>
    </div>
</div>
