<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page introuvable</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f9fafb;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; padding: 2rem;
        }
        .error-box { text-align: center; max-width: 400px; }
        .error-box h1 { font-size: 5rem; color: #94a3b8; font-weight: 800; }
        .error-box p { color: #6b7280; margin: 1rem 0 2rem; }
        .error-box a {
            background: #475569; color: white; padding: 0.75rem 1.5rem;
            border-radius: 0.75rem; text-decoration: none; font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>404</h1>
        <p>Cette page est introuvable.</p>
        <a href="javascript:history.back()">← Retour</a>
    </div>
</body>
</html>
