<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    require_once APP_PATH . '/models/Setting.php';
    $brand = $app_name ?? Context::name();
    ?>
    <title>Admin — <?= View::e($brand) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"></noscript>
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="<?= View::asset('css/main.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('css/admin.css') ?>">
</head>
<body class="admin-layout">

    <?php if (!empty($_SESSION['impersonating'])): ?>
    <div class="impersonation-banner">
        <i class="fa-solid fa-user-secret"></i>
        Mode SAV — Vous etes connecte en tant que <strong><?= View::e($_SESSION['user']['nom'] ?? '') ?></strong> (<?= View::e(Context::name()) ?>)
        <a href="<?= BASE_URL ?>/superadmin/stop-impersonation">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Revenir au super-admin
        </a>
    </div>
    <style>
        .impersonation-banner {
            position: sticky; top: 0; z-index: 1000;
            background: linear-gradient(90deg, #1e293b, #334155);
            color: #fbbf24;
            padding: .7rem 1rem;
            font-size: .85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: .8rem;
            border-bottom: 2px solid #f59e0b;
        }
        .impersonation-banner a {
            margin-left: auto;
            background: #f59e0b;
            color: #1f2937;
            padding: .3rem .8rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
        }
        .impersonation-banner a:hover { background: #fbbf24; }
    </style>
    <?php endif; ?>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <?php
        $brandLogo = '';
        try {
            $brandLogo = (new Setting(Context::id()))->get('logo', '');
        } catch (\Throwable $e) {}
        ?>
        <div class="sidebar__brand">
            <?php if ($brandLogo): ?>
                <img src="<?= View::asset($brandLogo) ?>" alt="<?= View::e($brand) ?>" style="width:32px;height:32px;border-radius:6px;object-fit:cover">
            <?php else: ?>
                <i class="fa-solid fa-utensils"></i>
            <?php endif; ?>
            <span><?= View::e($brand) ?></span>
        </div>

        <nav class="sidebar__nav">
            <?php $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); ?>

            <a href="<?= View::url('admin/dashboard') ?>"
               class="sidebar__link <?= str_contains($currentPath, '/admin/dashboard') ? 'sidebar__link--active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Dashboard</span>
            </a>

            <a href="<?= View::url('admin/menu') ?>"
               class="sidebar__link <?= str_contains($currentPath, '/admin/menu') ? 'sidebar__link--active' : '' ?>">
                <i class="fa-solid fa-book-open"></i>
                <span>Menu</span>
            </a>

            <a href="<?= View::url('admin/categories') ?>"
               class="sidebar__link <?= str_contains($currentPath, '/admin/categories') ? 'sidebar__link--active' : '' ?>">
                <i class="fa-solid fa-tags"></i>
                <span>Catégories</span>
            </a>

            <a href="<?= View::url('admin/tables') ?>"
               class="sidebar__link <?= str_contains($currentPath, '/admin/tables') ? 'sidebar__link--active' : '' ?>">
                <i class="fa-solid fa-table-cells"></i>
                <span>Tables & QR</span>
            </a>

            <a href="<?= View::url('admin/orders') ?>"
               class="sidebar__link <?= str_contains($currentPath, '/admin/orders') ? 'sidebar__link--active' : '' ?>">
                <i class="fa-solid fa-receipt"></i>
                <span>Commandes</span>
            </a>

            <a href="<?= View::url('admin/reports') ?>"
               class="sidebar__link <?= str_contains($currentPath, '/admin/reports') ? 'sidebar__link--active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Rapports</span>
            </a>

            <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
            <a href="<?= View::url('admin/users') ?>"
               class="sidebar__link <?= str_contains($currentPath, '/admin/users') ? 'sidebar__link--active' : '' ?>">
                <i class="fa-solid fa-users"></i>
                <span>Utilisateurs</span>
            </a>
            <a href="<?= View::url('admin/settings') ?>"
               class="sidebar__link <?= str_contains($currentPath, '/admin/settings') ? 'sidebar__link--active' : '' ?>">
                <i class="fa-solid fa-gear"></i>
                <span>Paramètres</span>
            </a>
            <?php endif; ?>

            <div class="sidebar__divider"></div>

            <a href="<?= View::url('kitchen') ?>" class="sidebar__link" target="_blank">
                <i class="fa-solid fa-fire-burner"></i>
                <span>Vue Cuisine</span>
            </a>
        </nav>

        <div class="sidebar__footer">
            <div class="sidebar__user">
                <div class="sidebar__user-avatar">
                    <?= strtoupper(substr($_SESSION['user']['nom'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="sidebar__user-info">
                    <span class="sidebar__user-name"><?= View::e($_SESSION['user']['nom'] ?? '') ?></span>
                    <span class="sidebar__user-role"><?= View::e($_SESSION['user']['role'] ?? '') ?></span>
                </div>
            </div>
            <form method="POST" action="<?= View::url('auth/logout') ?>" style="margin:0">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= View::e($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                <button type="submit" class="sidebar__logout" title="Déconnexion">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </form>
        </div>
    </aside>

    <!-- Overlay sidebar mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Contenu principal -->
    <div class="admin-content">
        <header class="admin-topbar">
            <button class="topbar-toggle" id="sidebarToggle" aria-label="Menu">
                <i class="fa-solid fa-bars"></i>
            </button>
        </header>

        <main class="admin-main">
            <?= $content ?>
        </main>
    </div>

    <script>
        const BASE_URL   = <?= json_encode(BASE_URL . (Context::hasContext() ? '/r/' . Context::slug() : '')) ?>;
        const CSRF_TOKEN = <?= json_encode($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>;
    </script>
    <script src="<?= View::asset('js/admin.js') ?>"></script>
</body>
</html>
