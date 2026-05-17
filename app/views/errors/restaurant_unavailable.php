<?php
/**
 * app/views/errors/restaurant_unavailable.php
 * Affichee aux clients qui scannent un QR d un restaurant inactif
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service indisponible</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #f8f5f0;
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
            max-width: 420px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,.08);
        }
        .icon {
            font-size: 4rem;
            color: #e85d04;
            margin-bottom: 1.5rem;
        }
        h1 { margin: 0 0 1rem; font-size: 1.4rem; font-weight: 800; }
        p { color: #6b7280; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon"><i class="fa-solid fa-bowl-food"></i></div>
        <h1>Service temporairement indisponible</h1>
        <p>Le restaurant n est pas accessible pour le moment.</p>
        <p>Veuillez demander de l aide a un serveur.</p>
    </div>
</body>
</html>
