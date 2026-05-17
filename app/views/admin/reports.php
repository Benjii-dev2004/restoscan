<?php
/**
 * app/views/admin/reports.php
 * Page Rapports/Analytics avec graphiques Chart.js
 */
$devise = '';
try {
    $devise = (new Setting(Context::id()))->get('devise', 'FCFA');
} catch (\Throwable $e) { $devise = 'FCFA'; }

// Helper local pour formater un nombre
$fmt = fn(float $n) => number_format($n, 0, ',', ' ');

// Helper pour les libelles boutons preset
$presets = [
    'today'      => "Aujourd'hui",
    'week'       => '7 derniers jours',
    'month'      => 'Mois en cours',
    'last_month' => 'Mois dernier',
    'q3m'        => '3 derniers mois',
    'year'       => 'Année en cours',
    'last_year'  => 'Année dernière',
    '5years'     => '5 dernières années',
];
?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fa-solid fa-chart-line"></i> Rapports & Statistiques
    </h1>
    <a href="<?= View::url('admin/reports/export?preset=' . urlencode($preset) . '&from=' . urlencode(substr($start, 0, 10)) . '&to=' . urlencode(substr($end, 0, 10))) ?>"
       class="btn btn--sm btn--outline">
        <i class="fa-solid fa-file-csv"></i> Exporter CSV
    </a>
</div>

<!-- Filtres période -->
<div class="reports-filters">
    <div class="reports-filters__presets">
        <?php foreach ($presets as $key => $label): ?>
        <a href="<?= View::url('admin/reports?preset=' . $key) ?>"
           class="reports-preset <?= $preset === $key ? 'reports-preset--active' : '' ?>">
            <?= View::e($label) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <form method="GET" action="<?= View::url('admin/reports') ?>" class="reports-custom">
        <input type="hidden" name="preset" value="custom">
        <label>Du <input type="date" name="from" value="<?= View::e(substr($start, 0, 10)) ?>"></label>
        <label>Au <input type="date" name="to"   value="<?= View::e(substr($end, 0, 10)) ?>"></label>
        <button type="submit" class="btn btn--sm btn--primary">
            <i class="fa-solid fa-magnifying-glass"></i> Filtrer
        </button>
    </form>
</div>

<p class="reports-period-label">
    Période analysée : <strong><?= date('d/m/Y', strtotime($start)) ?></strong>
    au <strong><?= date('d/m/Y', strtotime($end)) ?></strong>
</p>

<!-- Cartes de stats -->
<div class="stats-grid">
    <div class="stat-card stat-card--green">
        <div class="stat-card__icon"><i class="fa-solid fa-coins"></i></div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= $fmt((float) $stats['ca']) ?></div>
            <div class="stat-card__label">CA total (<?= View::e($devise) ?>)</div>
        </div>
    </div>

    <div class="stat-card stat-card--orange">
        <div class="stat-card__icon"><i class="fa-solid fa-receipt"></i></div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= (int) $stats['nb_cmd'] ?></div>
            <div class="stat-card__label">Commandes</div>
        </div>
    </div>

    <div class="stat-card stat-card--blue">
        <div class="stat-card__icon"><i class="fa-solid fa-basket-shopping"></i></div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= $fmt((float) $stats['panier_moyen']) ?></div>
            <div class="stat-card__label">Panier moyen (<?= View::e($devise) ?>)</div>
        </div>
    </div>

    <div class="stat-card stat-card--yellow">
        <div class="stat-card__icon"><i class="fa-solid fa-trophy"></i></div>
        <div class="stat-card__body">
            <div class="stat-card__value" style="font-size:1rem;line-height:1.2">
                <?= !empty($topDishes) ? View::e($topDishes[0]['nom']) : '—' ?>
            </div>
            <div class="stat-card__label">Plat n°1</div>
        </div>
    </div>
</div>

<!-- Graphique evolution CA -->
<div class="dashboard-card" style="margin-bottom:1.5rem">
    <div class="dashboard-card__header">
        <h2>
            <i class="fa-solid fa-chart-area"></i>
            Évolution du chiffre d'affaires
            <small style="font-weight:400;color:#94a3b8">
                (groupé par <?= match($grouping) { 'day'=>'jour', 'month'=>'mois', 'year'=>'année', default => $grouping } ?>)
            </small>
        </h2>
    </div>
    <div style="padding:1rem 1.5rem">
        <?php if (empty($evolution)): ?>
        <p class="text-muted">Aucune donnée sur cette période.</p>
        <?php else: ?>
        <canvas id="chart_evolution" style="max-height:300px"></canvas>
        <?php endif; ?>
    </div>
</div>

<div class="dashboard-cols">
    <!-- Heures de pointe -->
    <div class="dashboard-card">
        <div class="dashboard-card__header">
            <h2><i class="fa-solid fa-clock"></i> Heures de pointe</h2>
        </div>
        <div style="padding:1rem 1.5rem">
            <?php if (empty($hourly)): ?>
            <p class="text-muted">—</p>
            <?php else: ?>
            <canvas id="chart_hourly" style="max-height:240px"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top 10 plats -->
    <div class="dashboard-card">
        <div class="dashboard-card__header">
            <h2><i class="fa-solid fa-utensils"></i> Top 10 plats</h2>
        </div>
        <?php if (empty($topDishes)): ?>
        <p class="text-muted" style="padding:1rem 1.5rem">—</p>
        <?php else: ?>
        <table class="data-table" style="width:100%">
            <thead>
                <tr>
                    <th>Plat</th>
                    <th style="text-align:right">Qté</th>
                    <th style="text-align:right">CA généré</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($topDishes as $d): ?>
                <tr>
                    <td><?= View::e($d['nom']) ?></td>
                    <td style="text-align:right"><?= (int) $d['qte_totale'] ?></td>
                    <td style="text-align:right"><strong><?= $fmt((float) $d['ca_genere']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Performance par table -->
<div class="dashboard-card" style="margin-top:1.5rem">
    <div class="dashboard-card__header">
        <h2><i class="fa-solid fa-table-cells"></i> Performance par table</h2>
    </div>
    <?php if (empty($byTable)): ?>
    <p class="text-muted" style="padding:1rem 1.5rem">—</p>
    <?php else: ?>
    <table class="data-table" style="width:100%">
        <thead>
            <tr>
                <th>Table</th>
                <th style="text-align:right">Commandes</th>
                <th style="text-align:right">CA</th>
                <th style="text-align:right">% du total</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $caTotal = max(1, (float) $stats['ca']);
        foreach ($byTable as $t):
            $pct = ((float) $t['ca'] / $caTotal) * 100;
        ?>
            <tr>
                <td><strong>Table <?= (int) $t['numero'] ?></strong></td>
                <td style="text-align:right"><?= (int) $t['nb_cmd'] ?></td>
                <td style="text-align:right"><strong><?= $fmt((float) $t['ca']) ?></strong></td>
                <td style="text-align:right">
                    <span style="color:#6b7280"><?= number_format($pct, 1, ',', ' ') ?> %</span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<style>
.reports-filters {
    background: white;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.reports-filters__presets {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
}
.reports-preset {
    padding: .45rem .9rem;
    border-radius: 999px;
    background: #f3f4f6;
    color: #374151;
    text-decoration: none;
    font-size: .85rem;
    font-weight: 600;
    transition: all .15s;
}
.reports-preset:hover { background: #e5e7eb; }
.reports-preset--active {
    background: var(--color-primary, #e85d04);
    color: white;
}
.reports-custom {
    display: flex;
    gap: .6rem;
    align-items: center;
    font-size: .85rem;
    flex-wrap: wrap;
}
.reports-custom label { display: flex; gap: .3rem; align-items: center; }
.reports-custom input[type="date"] {
    padding: .4rem .6rem;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-family: inherit;
    font-size: .85rem;
}
.reports-period-label {
    color: #6b7280;
    font-size: .9rem;
    margin: 0 0 1rem;
}
.data-table { border-collapse: collapse; font-size: .9rem; }
.data-table th, .data-table td {
    padding: .7rem 1rem;
    border-bottom: 1px solid #f3f4f6;
}
.data-table th {
    text-align: left;
    background: #f9fafb;
    font-weight: 600;
    color: #6b7280;
    font-size: .8rem;
    text-transform: uppercase;
}
.data-table tr:last-child td { border-bottom: none; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const fmtMoney = n => new Intl.NumberFormat('fr-FR').format(n) + ' <?= addslashes($devise) ?>';

    // Evolution CA
    const evoData = <?= json_encode(array_map(fn($r) => [
        'bucket' => $r['bucket'],
        'ca'     => (float) $r['ca'],
        'nb'     => (int) $r['nb'],
    ], $evolution)) ?>;

    if (evoData.length > 0) {
        new Chart(document.getElementById('chart_evolution'), {
            type: 'line',
            data: {
                labels: evoData.map(r => r.bucket),
                datasets: [{
                    label: 'Chiffre d\'affaires',
                    data: evoData.map(r => r.ca),
                    borderColor: '#e85d04',
                    backgroundColor: 'rgba(232, 93, 4, 0.15)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#e85d04',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => fmtMoney(ctx.parsed.y) + ' (' + evoData[ctx.dataIndex].nb + ' cmd)'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => new Intl.NumberFormat('fr-FR').format(v) }
                    }
                }
            }
        });
    }

    // Heures de pointe
    const hourData = <?= json_encode(array_map(fn($r) => [
        'heure'  => (int) $r['heure'],
        'nb_cmd' => (int) $r['nb_cmd'],
    ], $hourly)) ?>;

    if (hourData.length > 0) {
        // Remplir les 24 heures (mettre 0 sur les heures manquantes)
        const full = Array.from({length:24}, (_,h) => {
            const r = hourData.find(x => x.heure === h);
            return r ? r.nb_cmd : 0;
        });
        new Chart(document.getElementById('chart_hourly'), {
            type: 'bar',
            data: {
                labels: full.map((_,h) => h + 'h'),
                datasets: [{
                    label: 'Commandes',
                    data: full,
                    backgroundColor: '#3b82f6',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }
})();
</script>
