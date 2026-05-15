/**
 * public/js/waiter.js
 * Interface serveur RESTOSCAN — polling + actions
 */

'use strict';

const servedIds = new Set();

document.addEventListener('DOMContentLoaded', () => {
    // Enregistrer les cards déjà affichées
    document.querySelectorAll('.waiter-card').forEach(el => {
        servedIds.add(el.id.replace('wcard-', ''));
    });

    startClock();
    startPolling();
});

/* ─── Horloge ────────────────────────────────────────────────────── */
function startClock() {
    const el = document.getElementById('waiterClock');
    if (!el) return;
    const tick = () => {
        el.textContent = new Date().toLocaleTimeString('fr-FR', {
            hour: '2-digit', minute: '2-digit'
        });
    };
    tick();
    setInterval(tick, 1000);
}

/* ─── Polling toutes les 3 secondes ─────────────────────────────── */
function startPolling() {
    setInterval(poll, 3000);
}

async function poll() {
    try {
        const res = await fetch(BASE_URL + '/waiter/poll');
        if (res.redirected || !res.ok) {
            window.location.href = BASE_URL + '/auth/login';
            return;
        }
        const data = await res.json();
        syncCards(data.orders ?? []);
    } catch {}
}

function syncCards(orders) {
    const activeIds = new Set(orders.map(o => String(o.id)));

    // Ajouter les nouvelles commandes prêtes
    orders.forEach(order => {
        const id = String(order.id);
        if (!servedIds.has(id)) {
            servedIds.add(id);
            addCard(order);
            showToast(`Table ${order.table_numero} — prête à servir !`);
        }
    });

    // Retirer les cards servies/disparues
    servedIds.forEach(id => {
        if (!activeIds.has(id)) {
            removeCard(id);
            servedIds.delete(id);
        }
    });

    updateCount();
}

/* ─── Ajouter une card ───────────────────────────────────────────── */
function addCard(order) {
    const container = document.getElementById('cardsPret');
    if (!container) return;

    container.querySelector('.waiter-empty')?.remove();

    const items = (order.items || []).map(i =>
        `<li class="waiter-card__item">
            <span class="waiter-card__qty">${i.quantite}×</span>
            <span class="waiter-card__name">${esc(i.nom)}</span>
            ${i.notes ? `<span class="waiter-card__note">${esc(i.notes)}</span>` : ''}
        </li>`
    ).join('');

    const note = order.notes
        ? `<div class="waiter-card__order-note"><i class="fa-solid fa-note-sticky"></i> ${esc(order.notes)}</div>`
        : '';

    const html = `
        <div class="waiter-card" id="wcard-${order.id}">
            <div class="waiter-card__header">
                <div class="waiter-card__table">
                    <i class="fa-solid fa-table-cells"></i>
                    Table ${order.table_numero}
                </div>
                <div class="waiter-card__time">
                    <i class="fa-regular fa-clock"></i>
                    ${formatTime(order.created_at)}
                </div>
            </div>
            <ul class="waiter-card__items">${items}</ul>
            ${note}
            <div class="waiter-card__total">
                Total : <strong>${formatPrice(order.total)}</strong>
            </div>
            <button class="waiter-card__btn" onclick="serveOrder(${order.id}, this)">
                <i class="fa-solid fa-check"></i>
                Servi — Table ${order.table_numero}
            </button>
        </div>`;

    const div = document.createElement('div');
    div.innerHTML = html;
    container.append(div.firstElementChild); // FIFO : plus ancien en haut, plus récent en bas
    updateCount();
}

/* ─── Supprimer une card ─────────────────────────────────────────── */
function removeCard(id) {
    const el = document.getElementById('wcard-' + id);
    if (!el) return;
    el.style.transition = 'all 0.3s ease';
    el.style.opacity    = '0';
    el.style.transform  = 'scale(0.95)';
    setTimeout(() => {
        el.remove();
        updateCount();
        checkEmpty();
    }, 300);
}

/* ─── Marquer comme servi ────────────────────────────────────────── */
async function serveOrder(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement…';

    try {
        const res  = await fetch(`${BASE_URL}/waiter/serve/${id}`, {
            method:  'POST',
            headers: { 'X-Csrf-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '' },
        });
        const data = await res.json();

        if (data.success) {
            servedIds.delete(String(id));
            removeCard(id);
            showToast('✓ Commande marquée comme servie', 'success');
        }
    } catch {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Réessayer';
    }
}

/* ─── Vérifier si la section est vide ───────────────────────────── */
function checkEmpty() {
    const container = document.getElementById('cardsPret');
    if (!container) return;
    if (!container.querySelector('.waiter-card')) {
        container.innerHTML = `
            <div class="waiter-empty" id="emptyPret">
                <i class="fa-solid fa-check-circle"></i>
                <p>Tout est servi — bien joué !</p>
            </div>`;
    }
}

/* ─── Mettre à jour le compteur ──────────────────────────────────── */
function updateCount() {
    const container = document.getElementById('cardsPret');
    const badge     = document.getElementById('countPret');
    if (!container || !badge) return;
    badge.textContent = container.querySelectorAll('.waiter-card').length;
}

/* ─── Toast ──────────────────────────────────────────────────────── */
function showToast(message) {
    const container = document.getElementById('waiterToasts');
    if (!container) return;

    const toast       = document.createElement('div');
    toast.className   = 'waiter-toast';
    toast.innerHTML   = `<i class="fa-solid fa-bell"></i> ${esc(message)}`;
    container.prepend(toast);

    setTimeout(() => {
        toast.style.transition = 'all 0.3s ease';
        toast.style.opacity    = '0';
        toast.style.transform  = 'translateX(20px)';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

/* ─── Utilitaires ────────────────────────────────────────────────── */
function esc(text) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(text)));
    return d.innerHTML;
}

function formatPrice(amount) {
    const devise = typeof DEVISE !== 'undefined' ? DEVISE : 'FCFA';
    return new Intl.NumberFormat('fr-FR').format(amount) + ' ' + devise;
}

function formatTime(datetime) {
    if (!datetime) return '';
    return new Date(datetime).toLocaleTimeString('fr-FR', {
        hour: '2-digit', minute: '2-digit'
    });
}
