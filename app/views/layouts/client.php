<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?= View::e($primary_color ?? '#e85d04') ?>">
    <title><?= View::e($app_name ?? 'RESTOSCAN') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= View::asset('css/main.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('css/client.css') ?>">
    <?php
    // Injection de la couleur principale depuis les paramètres du restaurant.
    // On calcule aussi une variante sombre (-15 %) pour les états hover/actif.
    $pc = preg_replace('/[^#a-fA-F0-9]/', '', $primary_color ?? '#e85d04');
    if (strlen($pc) === 7) {
        $r  = hexdec(substr($pc, 1, 2));
        $g  = hexdec(substr($pc, 3, 2));
        $b  = hexdec(substr($pc, 5, 2));
        $dr = max(0, (int)($r * 0.82));
        $dg = max(0, (int)($g * 0.82));
        $db = max(0, (int)($b * 0.82));
        $dark = sprintf('#%02x%02x%02x', $dr, $dg, $db);
    } else {
        $pc   = '#e85d04';
        $dark = '#c94d03';
    }
    ?>
    <style>
        :root {
            --color-primary:       <?= $pc ?>;
            --color-primary-dark:  <?= $dark ?>;
        }
    </style>
</head>
<body class="client-layout">
    <?= $content ?>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        const DEVISE   = <?= json_encode($devise ?? 'FCFA') ?>;
    </script>
    <script src="<?= View::asset('js/cart.js') ?>"></script>
</body>
</html>
