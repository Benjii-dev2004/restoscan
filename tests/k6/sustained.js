/**
 * tests/k6/sustained.js
 *
 * Scenario : SERVICE SOUTENU
 * Simule un service complet de 30 minutes :
 *  - 50 clients qui consultent le menu en continu
 *  - 5 nouvelles commandes / minute (sous le rate limit)
 *  - Polling de statut commandes par les clients
 *
 * Objectif : detecter les fuites memoire et instabilites long-terme.
 *
 * Usage :
 *   $env:BASE_URL = "https://restoscan.alwaysdata.net"
 *   $env:QR_TOKEN = "ton_token_64_caracteres"
 *   k6 run tests/k6/sustained.js
 */

import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'https://restoscan.alwaysdata.net';
const QR_TOKEN = __ENV.QR_TOKEN;
const MENU_ITEM_ID = parseInt(__ENV.MENU_ITEM_ID || '1', 10);

if (!QR_TOKEN) {
    throw new Error('QR_TOKEN env var required');
}

export const options = {
    scenarios: {
        // 50 clients qui consultent le menu en permanence
        menu_browsing: {
            executor: 'ramping-vus',
            startVUs: 10,
            stages: [
                { duration: '2m',  target: 50 },
                { duration: '26m', target: 50 },
                { duration: '2m',  target: 0  },
            ],
            exec: 'browseMenu',
        },

        // Commandes regulieres (5/min = sous le rate limit)
        steady_orders: {
            executor: 'constant-arrival-rate',
            rate: 5,
            timeUnit: '1m',
            duration: '30m',
            preAllocatedVUs: 3,
            maxVUs: 5,
            exec: 'placeOrder',
            startTime: '1m', // attend 1 min avant de commencer
        },
    },

    thresholds: {
        // Latence stable sur la duree
        http_req_duration: ['p(95)<1500'],
        http_req_failed:   ['rate<0.02'],
    },
};

export function browseMenu() {
    const r = http.get(`${BASE_URL}/menu/${QR_TOKEN}`);
    check(r, {
        'menu 200': (r) => r.status === 200,
    });
    sleep(Math.random() * 15 + 5); // 5-20s entre consultations
}

export function placeOrder() {
    const payload = JSON.stringify({
        qr_token: QR_TOKEN,
        notes: 'k6 sustained',
        items: [{ id: MENU_ITEM_ID, quantite: 1, notes: '' }],
    });

    const r = http.post(`${BASE_URL}/order/create`, payload, {
        headers: { 'Content-Type': 'application/json' },
    });

    const ok = check(r, {
        'order accepted (200 ou 429)': (r) => r.status === 200 || r.status === 429,
    });

    // Si commande acceptee, simule polling du statut pendant 30s
    if (r.status === 200) {
        const commandeId = r.json('commande_id');
        for (let i = 0; i < 5; i++) {
            sleep(6);
            http.get(`${BASE_URL}/order/status/${commandeId}`);
        }
    }
}

export function handleSummary(data) {
    const m = data.metrics;
    const lines = [
        '\n📊 RESULTAT SUSTAINED (30 min)',
        '──────────────────────────────',
        `✓ Requetes totales        : ${m.http_reqs.values.count}`,
        `✓ Duree moyenne           : ${m.http_req_duration.values.avg.toFixed(0)}ms`,
        `✓ p95                     : ${m.http_req_duration.values['p(95)'].toFixed(0)}ms`,
        `✓ p99                     : ${m.http_req_duration.values['p(99)'].toFixed(0)}ms`,
        `✓ Taux d'erreur           : ${(m.http_req_failed.values.rate * 100).toFixed(2)}%`,
        '',
        m.http_req_duration.values['p(95)'] < 1500 && m.http_req_failed.values.rate < 0.02
            ? '🟢 STABILITE OK sur 30 min'
            : '🔴 Degradation detectee — verifier les logs serveur',
        '',
    ];
    return { 'stdout': lines.join('\n') };
}
