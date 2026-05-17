/**
 * tests/k6/order_burst.js
 *
 * Scenario : PIC DE COMMANDES + verification du rate limit
 * Envoie 30 commandes en 1 minute depuis la meme IP.
 * Le rate limit doit en accepter 20 et en rejeter 10 (avec status 429).
 *
 * Si toutes passent ou que toutes echouent : le rate limit est cassé.
 *
 * Usage :
 *   $env:BASE_URL = "https://restoscan.alwaysdata.net"
 *   $env:QR_TOKEN = "ton_token_64_caracteres"
 *   k6 run tests/k6/order_burst.js
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'https://restoscan.alwaysdata.net';
const QR_TOKEN = __ENV.QR_TOKEN;
const MENU_ITEM_ID = parseInt(__ENV.MENU_ITEM_ID || '1', 10);

if (!QR_TOKEN) {
    throw new Error('QR_TOKEN env var required');
}

const success = new Counter('orders_success');
const rejected = new Counter('orders_rate_limited');

export const options = {
    scenarios: {
        burst: {
            executor: 'constant-arrival-rate',
            rate: 30,                  // 30 requetes
            timeUnit: '1m',            // par minute
            duration: '1m',
            preAllocatedVUs: 5,
            maxVUs: 10,
        },
    },

    thresholds: {
        // Verifie qu il y a EFFECTIVEMENT des 429 (sinon le rate limit est cassé)
        'orders_rate_limited': ['count>0'],
        // Verifie qu il y a EFFECTIVEMENT des succes (sinon tout est cassé)
        'orders_success': ['count>=15'],
    },
};

export default function () {
    const payload = JSON.stringify({
        qr_token: QR_TOKEN,
        notes: 'k6 burst test',
        items: [
            { id: MENU_ITEM_ID, quantite: 1, notes: '' }
        ],
    });

    const r = http.post(`${BASE_URL}/order/create`, payload, {
        headers: { 'Content-Type': 'application/json' },
        tags: { type: 'order' },
    });

    if (r.status === 200) {
        success.add(1);
        check(r, {
            'success a un commande_id': (r) => r.json('commande_id') > 0,
        });
    } else if (r.status === 429) {
        rejected.add(1);
        check(r, {
            'rate limit message present': (r) => r.body.includes('Trop'),
        });
    } else {
        console.error(`Unexpected status ${r.status}: ${r.body}`);
    }
}

export function handleSummary(data) {
    const m = data.metrics;
    const ok = m.orders_success ? m.orders_success.values.count : 0;
    const ko = m.orders_rate_limited ? m.orders_rate_limited.values.count : 0;
    const total = ok + ko;

    const lines = [
        '\n📊 RESULTAT ORDER BURST',
        '─────────────────────────',
        `✓ Tentatives totales      : ${total}`,
        `✓ Commandes acceptees     : ${ok}`,
        `✓ Bloquees par rate limit : ${ko}`,
        `✓ Taux d acceptation      : ${total > 0 ? ((ok / total) * 100).toFixed(1) : 0}%`,
        '',
        ok >= 15 && ko > 0
            ? '🟢 RATE LIMIT FONCTIONNE : protection effective'
            : ok === 0
                ? '🔴 PROBLEME : aucune commande acceptee'
                : ko === 0
                    ? '🔴 PROBLEME : rate limit ne bloque rien (DoS possible)'
                    : '🟡 Resultat partiel',
        '',
    ];
    return { 'stdout': lines.join('\n') };
}
