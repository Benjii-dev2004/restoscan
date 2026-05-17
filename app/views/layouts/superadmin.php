<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin — RESTOSCAN</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --sa-bg:       #0f172a;
            --sa-bg2:      #1e293b;
            --sa-card:     #1e293b;
            --sa-border:   #334155;
            --sa-text:     #f1f5f9;
            --sa-muted:    #94a3b8;
            --sa-accent:   #f97316;
            --sa-green:    #22c55e;
            --sa-red:      #ef4444;
            --sa-yellow:   #eab308;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--sa-bg);
            color: var(--sa-text);
            min-height: 100vh;
        }
        a { color: var(--sa-accent); text-decoration: none; }
        .sa-header {
            background: var(--sa-bg2);
            border-bottom: 1px solid var(--sa-border);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sa-header__brand {
            font-weight: 800;
            font-size: 1.2rem;
            display: flex;
            gap: .6rem;
            align-items: center;
        }
        .sa-header__brand i { color: var(--sa-accent); }
        .sa-header__user {
            display: flex;
            gap: 1rem;
            align-items: center;
            color: var(--sa-muted);
            font-size: .9rem;
        }
        .sa-logout {
            background: transparent;
            border: 1px solid var(--sa-border);
            color: var(--sa-text);
            padding: .4rem .9rem;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
        }
        .sa-logout:hover { background: var(--sa-red); border-color: var(--sa-red); }
        .sa-main { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .sa-page-title {
            font-size: 1.6rem;
            font-weight: 800;
            margin: 0 0 1.5rem;
            display: flex;
            gap: .7rem;
            align-items: center;
        }
        .sa-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .sa-stat {
            background: var(--sa-card);
            border: 1px solid var(--sa-border);
            border-radius: 10px;
            padding: 1.2rem;
        }
        .sa-stat__value { font-size: 2rem; font-weight: 800; line-height: 1; }
        .sa-stat__label { color: var(--sa-muted); font-size: .85rem; margin-top: .5rem; }
        .sa-stat--green  .sa-stat__value { color: var(--sa-green); }
        .sa-stat--red    .sa-stat__value { color: var(--sa-red); }
        .sa-stat--yellow .sa-stat__value { color: var(--sa-yellow); }
        .sa-card {
            background: var(--sa-card);
            border: 1px solid var(--sa-border);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .sa-card__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .sa-card__header h2 { margin: 0; font-size: 1.1rem; }
        .sa-btn {
            background: var(--sa-accent);
            color: white;
            border: none;
            padding: .55rem 1rem;
            border-radius: 6px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-size: .9rem;
        }
        .sa-btn:hover { opacity: .9; }
        .sa-btn--ghost {
            background: transparent;
            border: 1px solid var(--sa-border);
            color: var(--sa-text);
        }
        .sa-btn--red    { background: var(--sa-red); }
        .sa-btn--yellow { background: var(--sa-yellow); color: #1a1300; }
        .sa-btn--sm     { padding: .3rem .65rem; font-size: .8rem; }
        .sa-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .9rem;
        }
        .sa-table th, .sa-table td {
            padding: .8rem;
            text-align: left;
            border-bottom: 1px solid var(--sa-border);
        }
        .sa-table th {
            background: var(--sa-bg);
            font-weight: 600;
            color: var(--sa-muted);
            text-transform: uppercase;
            font-size: .75rem;
            letter-spacing: .05em;
        }
        .sa-badge {
            display: inline-block;
            padding: .2rem .6rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 700;
        }
        .sa-badge--actif    { background: rgba(34,197,94,.15);  color: var(--sa-green); }
        .sa-badge--suspendu { background: rgba(234,179,8,.15);  color: var(--sa-yellow); }
        .sa-badge--expire   { background: rgba(239,68,68,.15);  color: var(--sa-red); }
        .sa-flash {
            padding: 1rem 1.2rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        .sa-flash--success {
            background: rgba(34,197,94,.15);
            color: var(--sa-green);
            border: 1px solid rgba(34,197,94,.3);
        }
        .sa-flash--error {
            background: rgba(239,68,68,.15);
            color: var(--sa-red);
            border: 1px solid rgba(239,68,68,.3);
        }
        .sa-form { display: grid; gap: 1rem; max-width: 600px; }
        .sa-form label { display: block; font-weight: 600; margin-bottom: .3rem; font-size: .9rem; }
        .sa-form input, .sa-form select, .sa-form textarea {
            width: 100%;
            padding: .65rem .9rem;
            background: var(--sa-bg);
            border: 1px solid var(--sa-border);
            border-radius: 6px;
            color: var(--sa-text);
            font-family: inherit;
            font-size: .9rem;
        }
        .sa-form input:focus, .sa-form select:focus { outline: none; border-color: var(--sa-accent); }
        .sa-form__row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 600px) {
            .sa-main { padding: 1rem; }
            .sa-form__row { grid-template-columns: 1fr; }
        }
        .sa-actions-inline { display: flex; gap: .4rem; flex-wrap: wrap; }
        .sa-inline-form { display: inline; }
        .sa-section-title { font-size: .9rem; color: var(--sa-muted); text-transform: uppercase; letter-spacing: .05em; margin: 1.5rem 0 .5rem; font-weight: 700; }
    </style>
</head>
<body>
    <header class="sa-header">
        <div class="sa-header__brand">
            <i class="fa-solid fa-shield-halved"></i>
            <span>RESTOSCAN — Super Admin</span>
        </div>
        <div class="sa-header__user">
            <span><?= View::e($sa['nom'] ?? '') ?></span>
            <form method="POST" action="<?= View::url('superadmin/logout') ?>" style="margin:0">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= View::e($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
                <button type="submit" class="sa-logout"><i class="fa-solid fa-right-from-bracket"></i> Deconnexion</button>
            </form>
        </div>
    </header>
    <main class="sa-main">
        <?= $content ?>
    </main>
</body>
</html>
