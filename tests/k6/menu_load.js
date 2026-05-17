/**
 * tests/k6/menu_load.js
 *
 * Scenario : RUSH MIDI
 * 100 clients qui scannent le QR code et browsent le menu en meme temps.
 * Simule l'instant ou un restaurant plein se met a commander.
 *
 * Usage :
 *   $env:BASE_URL = "https://restoscan.alwaysdata.net"
 *   $env:QR_TOKEN = "ton_token_64_caracteres"
 *   k6 run tests/k6/menu_load.js
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'https://restoscan.alwaysdata.net';
const QR_TOKEN = __ENV.QR_TOKEN;

if (!QR_TOKEN) {
    throw new Error('QR_TOKEN env var required');
}

// Metrique custom : taux d'erreur
const errorRate = new Rate('errors');

export const options = {
    stages: [
        { duration: '1m', target: 100 },  // Montee en charge 0 -> 100 VUs
        { duration: '3m', target: 100 },  // Plateau a 100 VUs (le vrai test)
        { duration: '1m', target: 0   },  // Descente
    ],

    // Demander a k6 de calculer p99 et p99.9 (pas dispo par defaut)
    summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],

    thresholds: {
        // 95% des requetes finissent en moins de 1s
        http_req_duration: ['p(95)<1000'],
        // Moins de 1% d'erreur
        errors: ['rate<0.01'],
        // Aucune requete > 3s (vraiment bloque)
        'http_req_duration{type:menu}': ['p(99)<3000'],
    },
};

export default function () {
    // Un client charge le menu, le consulte 30-60 sec, parfois reload

    // 1. Premier chargement (depuis QR scan)
    const r1 = http.get(`${BASE_URL}/menu/${QR_TOKEN}`, {
        tags: { type: 'menu' },
    });
    const ok1 = check(r1, {
        'menu 200':            (r) => r.status === 200,
        'menu contient table': (r) => r.body && r.body.includes('Table'),
        'menu < 2s':           (r) => r.timings.duration < 2000,
    });
    errorRate.add(!ok1);

    // 2. Le client lit le menu (pause realiste)
    sleep(Math.random() * 5 + 3); // 3-8s

    // 3. Charge un asset CSS (verifie le cache)
    const r2 = http.get(`${BASE_URL}/public/css/client.css`, {
        tags: { type: 'asset' },
    });
    check(r2, {
        'css 200':       (r) => r.status === 200,
        'css cache hit': (r) => r.headers['Cache-Control'] !== undefined,
    });

    // 4. Pause "le client choisit ses plats"
    sleep(Math.random() * 10 + 5); // 5-15s
}

export function handleSummary(data) {
    return {
        'stdout': textSummary(data),
        'tests/k6/results/menu_load.json': JSON.stringify(data, null, 2),
    };
}

function textSummary(data) {
    const m = data.metrics;
    const v = m.http_req_duration ? m.http_req_duration.values : {};
    const get = (k) => (v[k] !== undefined && v[k] !== null) ? v[k].toFixed(0) : 'n/a';
    const p95 = v['p(95)'] ?? 0;

    const lines = [
        '\n📊 RESULTAT MENU LOAD',
        '────────────────────────',
        `✓ Requetes totales        : ${m.http_reqs ? m.http_reqs.values.count : 0}`,
        `✓ Duree moyenne           : ${get('avg')}ms`,
        `✓ Duree mediane           : ${get('med')}ms`,
        `✓ p95 (95% sous)          : ${get('p(95)')}ms`,
        `✓ p99                     : ${get('p(99)')}ms`,
        `✓ Max                     : ${get('max')}ms`,
        `✓ Taux d'erreur HTTP      : ${m.http_req_failed ? (m.http_req_failed.values.rate * 100).toFixed(2) : 0}%`,
        '',
        p95 < 1000
            ? '🟢 OBJECTIF p95<1s : ATTEINT'
            : '🔴 OBJECTIF p95<1s : DEPASSE (probable bottleneck hebergeur)',
        '',
    ];
    return lines.join('\n');
}
