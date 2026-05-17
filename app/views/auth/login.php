<?php
/**
 * app/views/auth/login.php
 * Page de login branded au nom et logo du restaurant.
 */
$primaryColor = $primary ?? '#e85d04';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — <?= View::e($app_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: <?= View::e($primaryColor) ?>; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(ellipse at top, rgba(255,255,255,0.1), transparent),
                linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-page { width: 100%; max-width: 420px; }
        .login-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }
        .login-card__logo { text-align: center; margin-bottom: 2rem; }
        .login-card__logo img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 1rem;
            margin-bottom: 1rem;
            object-fit: contain;
        }
        .login-card__logo .fa-utensils {
            font-size: 3rem;
            color: var(--primary);
            display: block;
            margin-bottom: 0.75rem;
        }
        .login-card__logo h1 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1a1a2e;
            letter-spacing: -0.5px;
        }
        .login-card__logo p {
            color: #6b7280;
            font-size: 0.9rem;
            margin-top: 0.4rem;
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
        .form-label i { color: var(--primary); margin-right: 0.25rem; }
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
        .form-input:focus { border-color: var(--primary); }
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
            justify-content: center;
        }
        .btn--primary { background: var(--primary); color: white; }
        .btn--primary:hover { opacity: 0.92; transform: translateY(-1px); }
        .btn--full { width: 100%; margin-top: 0.5rem; }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-card__logo">
                <?php if (!empty($logo)): ?>
                    <img src="<?= View::asset($logo) ?>" alt="<?= View::e($app_name) ?>">
                <?php else: ?>
                    <i class="fa-solid fa-utensils"></i>
                <?php endif; ?>
                <h1><?= View::e($app_name) ?></h1>
                <?php if (!empty($slogan)): ?>
                <p><?= View::e($slogan) ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($error)): ?>
            <div class="alert alert--error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= View::e($error) ?>
            </div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="<?= View::url('auth/login') ?>">
                <?= View::csrfField() ?>

                <div class="form-group">
                    <label class="form-label" for="email"><i class="fa-solid fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="votre@email.com" required autocomplete="email">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password"><i class="fa-solid fa-lock"></i> Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn--primary btn--full">
                    Se connecter
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
