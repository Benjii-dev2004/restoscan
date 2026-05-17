<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    require_once APP_PATH . '/models/Setting.php';
    $brand     = $app_name ?? Context::name();
    $brandLogo = '';
    try { $brandLogo = (new Setting(Context::id()))->get('logo', ''); } catch (\Throwable $e) {}
    ?>
    <title>Cuisine — <?= View::e($brand) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"></noscript>
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="<?= View::asset('css/main.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('css/kitchen.css') ?>">
</head>
<body class="kitchen-layout">
    <?php if (!empty($_SESSION['impersonating'])): ?>
    <div style="background:linear-gradient(90deg,#1e293b,#334155);color:#fbbf24;padding:.7rem 1rem;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:.8rem;border-bottom:2px solid #f59e0b">
        <i class="fa-solid fa-user-secret"></i> Mode SAV — <?= View::e(Context::name()) ?>
        <a href="<?= BASE_URL ?>/superadmin/stop-impersonation" style="margin-left:auto;background:#f59e0b;color:#1f2937;padding:.3rem .8rem;border-radius:6px;text-decoration:none;font-weight:700">Revenir</a>
    </div>
    <?php endif; ?>
    <header class="kitchen-header">
        <div class="kitchen-header__logo">
            <?php if ($brandLogo): ?>
                <img src="<?= View::asset($brandLogo) ?>" alt="<?= View::e($brand) ?>" style="width:28px;height:28px;border-radius:6px;object-fit:cover">
            <?php else: ?>
                <i class="fa-solid fa-utensils"></i>
            <?php endif; ?>
            <span><?= View::e($brand) ?> — Cuisine</span>
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
        const BASE_URL   = <?= json_encode(BASE_URL . (Context::hasContext() ? '/r/' . Context::slug() : '')) ?>;
        const CSRF_TOKEN = <?= json_encode($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>;
    </script>
    <script src="<?= View::asset('js/kitchen.js') ?>"></script>
</body>
</html>
