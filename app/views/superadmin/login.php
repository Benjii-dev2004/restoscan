<?php
/**
 * app/views/superadmin/login.php
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin — Connexion</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-box {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,.4);
        }
        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
            color: #f1f5f9;
        }
        .login-brand i {
            font-size: 2.5rem;
            color: #f97316;
            display: block;
            margin-bottom: .8rem;
        }
        .login-brand h1 { margin: 0; font-size: 1.4rem; font-weight: 800; }
        .login-brand p { margin: .3rem 0 0; color: #94a3b8; font-size: .85rem; }
        .login-error {
            background: rgba(239,68,68,.15);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,.3);
            padding: .8rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: .85rem;
        }
        .login-form label { display: block; color: #94a3b8; font-size: .85rem; margin-bottom: .3rem; font-weight: 600; }
        .login-form input {
            width: 100%;
            padding: .8rem 1rem;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #f1f5f9;
            font-family: inherit;
            font-size: .95rem;
            margin-bottom: 1.2rem;
            box-sizing: border-box;
        }
        .login-form input:focus { outline: none; border-color: #f97316; }
        .login-form button {
            width: 100%;
            background: #f97316;
            color: white;
            border: none;
            padding: .9rem;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 700;
            font-size: .95rem;
            cursor: pointer;
        }
        .login-form button:hover { opacity: .9; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="login-brand">
            <i class="fa-solid fa-shield-halved"></i>
            <h1>RESTOSCAN</h1>
            <p>Espace Super Administrateur</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="login-error"><i class="fa-solid fa-circle-exclamation"></i> <?= View::e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= View::url('superadmin/login') ?>" class="login-form" autocomplete="off">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= View::e($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">

            <!-- Champs leurres anti-autofill agressif -->
            <input type="text" name="fake_user_decoy" style="display:none" tabindex="-1" autocomplete="username">
            <input type="password" name="fake_pass_decoy" style="display:none" tabindex="-1" autocomplete="current-password">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">

            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required autocomplete="new-password">

            <button type="submit"><i class="fa-solid fa-arrow-right-to-bracket"></i> Se connecter</button>
        </form>
    </div>

    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                const e = document.getElementById('email');
                const p = document.getElementById('password');
                if (e && !e.matches(':focus')) e.value = '';
                if (p && !p.matches(':focus')) p.value = '';
            }, 100);
        });
    </script>
</body>
</html>
