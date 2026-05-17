<?php
/**
 * app/views/errors/subscription_blocked.php
 * Affichee aux users admin/cuisine/serveur quand le restaurant n est plus actif
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acces suspendu</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fff7ed 0%, #fee2e2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            color: #1f2937;
        }
        .box {
            background: white;
            border-radius: 16px;
            padding: 3rem 2rem;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,.1);
        }
        .icon {
            font-size: 4rem;
            color: #ef4444;
            margin-bottom: 1.5rem;
        }
        h1 { margin: 0 0 1rem; font-size: 1.6rem; font-weight: 800; }
        p { color: #6b7280; line-height: 1.6; margin: .5rem 0; }
        .info {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            text-align: left;
            border-radius: 6px;
            margin: 1.5rem 0;
            font-size: .9rem;
        }
        .btn-logout {
            display: inline-block;
            margin-top: 1.5rem;
            color: #6b7280;
            font-size: .85rem;
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon"><i class="fa-solid fa-circle-exclamation"></i></div>
        <h1>Acces temporairement bloque</h1>

        <?php
        $statut = $restaurant['statut'] ?? 'expire';
        if ($statut === 'suspendu'):
        ?>
        <p>Votre acces a RESTOSCAN a ete suspendu par l administration.</p>
        <p>Veuillez contacter le support pour regulariser votre situation.</p>
        <?php else: ?>
        <p>Votre abonnement RESTOSCAN a expire.</p>
        <?php if (!empty($restaurant['abonnement_fin'])): ?>
        <p>Date d expiration : <strong><?= date('d/m/Y', strtotime($restaurant['abonnement_fin'])) ?></strong></p>
        <?php endif; ?>
        <?php endif; ?>

        <div class="info">
            <strong><i class="fa-solid fa-phone"></i> Pour renouveler ou regler votre situation :</strong><br>
            Contactez l equipe RESTOSCAN au plus vite.
        </div>

        <form method="POST" action="<?= View::url('auth/logout') ?>" style="margin:0">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= View::e($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
            <button type="submit" class="btn-logout" style="background:none;border:none;cursor:pointer;text-decoration:underline">
                Se deconnecter
            </button>
        </form>
    </div>
</body>
</html>
