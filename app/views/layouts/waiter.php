<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service — <?= View::e($app_name ?? 'RESTOSCAN') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= View::asset('css/main.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('css/waiter.css') ?>">
</head>
<body class="waiter-layout">

    <header class="waiter-header">
        <div class="waiter-header__left">
            <div class="waiter-header__logo">
                <i class="fa-solid fa-bell-concierge"></i>
                <span><?= View::e($app_name ?? 'RESTOSCAN') ?> — Service</span>
            </div>
        </div>
        <div class="waiter-header__right">
            <span class="waiter-header__user">
                <i class="fa-solid fa-user"></i>
                <?= View::e($user['nom'] ?? '') ?>
            </span>
            <span class="waiter-clock" id="waiterClock"></span>
            <form method="POST" action="<?= View::url('auth/logout') ?>" style="margin:0">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= View::e($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                <button type="submit" class="waiter-logout" title="Déconnexion">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </form>
        </div>
    </header>

    <main class="waiter-main">
        <?= $content ?>
    </main>

    <script>
        const BASE_URL   = <?= json_encode(BASE_URL) ?>;
        const CSRF_TOKEN = <?= json_encode($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>;
    </script>
    <script src="<?= View::asset('js/waiter.js') ?>"></script>
</body>
</html>
