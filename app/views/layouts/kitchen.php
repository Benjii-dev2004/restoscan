<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuisine — <?= View::e($app_name ?? 'RESTOSCAN') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= View::asset('css/main.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('css/kitchen.css') ?>">
</head>
<body class="kitchen-layout">
    <header class="kitchen-header">
        <div class="kitchen-header__logo">
            <i class="fa-solid fa-utensils"></i>
            <span><?= View::e($app_name ?? 'RESTOSCAN') ?> — Cuisine</span>
        </div>
        <div class="kitchen-header__meta">
            <span id="kitchen-clock" class="kitchen-clock"></span>
            <span class="kitchen-user">
                <i class="fa-solid fa-user-chef"></i>
                <?= View::e($user['nom'] ?? '') ?>
            </span>
            <form method="POST" action="<?= View::url('auth/logout') ?>" style="margin:0">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= View::e($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                <button type="submit" class="btn-logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </form>
        </div>
    </header>

    <main class="kitchen-main">
        <?= $content ?>
    </main>

    <script>
        const BASE_URL   = <?= json_encode(BASE_URL) ?>;
        const CSRF_TOKEN = <?= json_encode($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>;
    </script>
    <script src="<?= View::asset('js/kitchen.js') ?>"></script>
</body>
</html>
