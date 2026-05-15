<div class="login-page">
    <div class="login-card">
        <div class="login-card__logo">
            <i class="fa-solid fa-qrcode"></i>
            <h1><?= View::e($app_name) ?></h1>
            <p>Gestion de restaurant</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert--error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <?= View::e($error) ?>
        </div>
        <?php endif; ?>

        <form class="login-form" method="POST" action="<?= View::url('auth/login') ?>">
            <?= View::csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="email">
                    <i class="fa-solid fa-envelope"></i> Email
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    placeholder="votre@email.com"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">
                    <i class="fa-solid fa-lock"></i> Mot de passe
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-input"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn btn--primary btn--full">
                Se connecter
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>
    </div>
</div>

<style>
/* Styles inline pour la page de login (page autonome sans layout) */
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.login-page {
    width: 100%;
    max-width: 420px;
}
.login-card {
    background: white;
    border-radius: 1.5rem;
    padding: 2.5rem 2rem;
    box-shadow: 0 25px 50px rgba(0,0,0,0.4);
}
.login-card__logo {
    text-align: center;
    margin-bottom: 2rem;
}
.login-card__logo i {
    font-size: 3rem;
    color: #e85d04;
    display: block;
    margin-bottom: 0.75rem;
}
.login-card__logo h1 {
    font-size: 1.75rem;
    font-weight: 800;
    color: #1a1a2e;
    letter-spacing: -0.5px;
}
.login-card__logo p {
    color: #6b7280;
    font-size: 0.9rem;
    margin-top: 0.25rem;
}
.alert {
    padding: 0.875rem 1rem;
    border-radius: 0.75rem;
    margin-bottom: 1.25rem;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.alert--error {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.form-group { margin-bottom: 1.25rem; }
.form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}
.form-label i { color: #e85d04; margin-right: 0.25rem; }
.form-input {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 0.75rem;
    font-size: 1rem;
    font-family: 'Inter', sans-serif;
    transition: border-color 0.2s;
    outline: none;
}
.form-input:focus { border-color: #e85d04; }
.btn {
    padding: 0.875rem 1.5rem;
    border: none;
    border-radius: 0.75rem;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Inter', sans-serif;
}
.btn--primary {
    background: #e85d04;
    color: white;
}
.btn--primary:hover { background: #c94d03; transform: translateY(-1px); }
.btn--full { width: 100%; justify-content: center; margin-top: 0.5rem; }
</style>
