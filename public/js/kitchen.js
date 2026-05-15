/**
 * public/js/kitchen.js
 * Interface cuisine RESTOSCAN — polling temps réel + gestion Kanban
 * Short polling toutes les 3 secondes via GET /kitchen/poll
 */

'use strict';

/* ─── État connu des commandes ───────────────────────────────────── */
const knownOrderIds = new Set();

/* ─── Initialisation ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    // Enregistrer les tickets déjà affichés
    document.querySelectorAll('.ticket').forEach(el => {
        knownOrderIds.add(el.id.replace('ticket-', ''));
    });

    updateCounts();
    startClock();
    startPolling();
    startTimers();
});

/* ─── Horloge ────────────────────────────────────────────────────── */
function startClock() {
    const el = document.getElementById('kitchen-clock');
    if (!el) return;
    const update = () => {
        el.textContent = new Date().toLocaleTimeString('fr-FR', {
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    };
    update();
    setInterval(update, 1000);
}

/* ─── Polling ────────────────────────────────────────────────────── */
function startPolling() {
    setInterval(poll, 3000);
}

async function poll() {
    try {
        const res = await fetch(BASE_URL + '/kitchen/poll');
        if (res.redirected || !res.ok) {
            window.location.href = BASE_URL + '/auth/login';
            return;
        }
        const data = await res.json();
        processOrders(data.orders ?? []);
    } catch {}
}

function processOrders(orders) {
    const activeIds = new Set(orders.map(o => String(o.id)));

    // Détecter les nouvelles commandes
    orders.forEach(order => {
        const idStr = String(order.id);
        if (!knownOrderIds.has(idStr)) {
            knownOrderIds.add(idStr); // BUG-18 : enregistrer aussi les en_preparation
            if (order.statut === 'en_attente') {
                addTicket(order);
                playNewOrderSound();
                showToast(`Nouvelle commande — Table ${order.table_numero}`);
            }
        }
    });

    // Retirer les tickets des commandes disparues (servies/annulées)
    knownOrderIds.forEach(id => {
        if (!activeIds.has(id)) {
            removeTicket(id);
            knownOrderIds.delete(id);
        }
    });

    updateCounts();
}

/* ─── Ajouter un ticket dans la bonne colonne ────────────────────── */
function addTicket(order) {
    const colId   = 'cards-' + order.statut;
    const colEl   = document.getElementById(colId);
    if (!colEl) return;

    // Retirer le message "vide" s'il existe
    const empty = colEl.querySelector('.kanban__empty');
    if (empty) empty.remove();

    const ticketHtml = buildTicketHtml(order);
    const div        = document.createElement('div');
    div.innerHTML    = ticketHtml;
    colEl.append(div.firstElementChild); // FIFO : nouvelles commandes en bas
}

function buildTicketHtml(order) {
    const itemsHtml = (order.items || []).map(item => `
        <li class="ticket__item">
            <span class="ticket__qty">${item.quantite}×</span>
            <span class="ticket__name">${escapeHtml(item.nom)}</span>
            ${item.notes ? `<span class="ticket__note">${escapeHtml(item.notes)}</span>` : ''}
        </li>
    `).join('');

    const noteHtml = order.notes ? `
        <div class="ticket__order-note">
            <i class="fa-solid fa-note-sticky"></i> ${escapeHtml(order.notes)}
        </div>
    ` : '';

    const nextStatut = order.statut === 'en_attente' ? 'en_preparation' : 'pret';
    const nextLabel  = order.statut === 'en_attente' ? 'Accepter' : 'Prêt !';
    const btnClass   = order.statut === 'en_attente' ? 'ticket__btn--accept' : 'ticket__btn--ready';

    return `
        <div class="ticket" id="ticket-${order.id}">
            <div class="ticket__header">
                <span class="ticket__table">
                    <i class="fa-solid fa-table-cells"></i> Table ${order.table_numero}
                </span>
                <span class="ticket__time">
                    <i class="fa-regular fa-clock"></i>
                    <span class="ticket-timer" data-start="${order.created_ts_ms ?? 0}">
                        ${order.minutes_elapsed ?? 0}min
                    </span>
                </span>
            </div>
            <ul class="ticket__items">${itemsHtml}</ul>
            ${noteHtml}
            <button class="ticket__btn ${btnClass}"
                onclick="kitchenUpdate(${order.id}, '${nextStatut}', this)">
                ${nextLabel} <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    `;
}

/* ─── Supprimer un ticket ────────────────────────────────────────── */
function removeTicket(id) {
    const el = document.getElementById('ticket-' + id);
    if (!el) return;
    el.style.opacity  = '0';
    el.style.transform = 'scale(0.95)';
    el.style.transition = 'all 0.3s ease';
    setTimeout(() => el.remove(), 300);
}

/* ─── Mettre à jour le statut d'une commande (AJAX) ─────────────── */
async function kitchenUpdate(commandeId, newStatut, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    try {
        const form = new FormData();
        form.append('statut', newStatut);

        const res  = await fetch(`${BASE_URL}/kitchen/update/${commandeId}`, {
            method:  'POST',
            headers: { 'X-Csrf-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '' },
            body:    form,
        });
        const data = await res.json();

        if (data.success) {
            moveTicket(commandeId, newStatut);
        } else {
            btn.disabled = false;
            btn.innerHTML = 'Erreur — Réessayer <i class="fa-solid fa-arrow-right"></i>';
        }
    } catch {
        btn.disabled = false;
        btn.innerHTML = 'Erreur — Réessayer <i class="fa-solid fa-arrow-right"></i>';
    }
}

/* ─── Déplacer un ticket vers la colonne suivante ────────────────── */
function moveTicket(commandeId, newStatut) {
    const ticket = document.getElementById('ticket-' + commandeId);
    if (!ticket) return;

    // Déterminer la prochaine colonne et le prochain bouton
    let nextStatut = '', nextLabel = '', btnClass = '';
    if (newStatut === 'en_preparation') {
        nextStatut = 'pret';
        nextLabel  = 'Prêt !';
        btnClass   = 'ticket__btn--ready';
    }

    // Mettre à jour le bouton dans le ticket
    const btn = ticket.querySelector('.ticket__btn');
    if (btn) {
        if (nextStatut) {
            btn.className = `ticket__btn ${btnClass}`;
            btn.setAttribute('onclick', `kitchenUpdate(${commandeId}, '${nextStatut}', this)`);
            btn.innerHTML = `${nextLabel} <i class="fa-solid fa-arrow-right"></i>`;
            btn.disabled  = false;
        } else {
            btn.remove();
        }
    }

    const targetCol = document.getElementById('cards-' + newStatut);
    if (targetCol) {
        // Supprimer l'indicateur vide si présent
        targetCol.querySelector('.kanban__empty')?.remove();
        targetCol.prepend(ticket);

        // Animation
        ticket.style.opacity   = '0';
        ticket.style.transform = 'translateY(-8px)';
        requestAnimationFrame(() => {
            ticket.style.transition = 'all 0.3s ease';
            ticket.style.opacity    = '1';
            ticket.style.transform  = 'translateY(0)';
        });
    }

    updateCounts();
}

/* ─── Compter les tickets par colonne ────────────────────────────── */
function updateCounts() {
    ['en_attente', 'en_preparation', 'pret'].forEach(statut => {
        const col   = document.getElementById('cards-' + statut);
        const count = document.getElementById('count-' + statut);
        if (!col || !count) return;
        const n = col.querySelectorAll('.ticket').length;
        count.textContent = n;
    });
}

/* ─── Timers de temps écoulé ─────────────────────────────────────── */
function startTimers() {
    setInterval(updateTimers, 60000); // toutes les minutes
}

function updateTimers() {
    document.querySelectorAll('.ticket-timer').forEach(el => {
        // data-start est un timestamp Unix en ms → parsing sans ambiguïté timezone
        const startMs = parseInt(el.dataset.start, 10);
        if (!startMs) return;
        const elapsed = Math.max(0, Math.floor((Date.now() - startMs) / 60000));
        el.textContent = elapsed + 'min';

        const ticket = el.closest('.ticket');
        if (ticket) {
            ticket.classList.toggle('ticket--warning', elapsed >= 10 && elapsed < 20);
            ticket.classList.toggle('ticket--urgent',  elapsed >= 20);
        }
    });
}

/* ─── Son pour nouvelle commande (Web Audio API) ─────────────────── */
function playNewOrderSound() {
    try {
        const ctx  = new (window.AudioContext || window.webkitAudioContext)();
        const osc  = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.setValueAtTime(660, ctx.currentTime + 0.15);
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.5);
    } catch {}
}

/* ─── Toast notification ─────────────────────────────────────────── */
function showToast(message) {
    const container = document.getElementById('kitchenToast');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className  = 'toast';
    toast.innerHTML  = `<i class="fa-solid fa-bell"></i> ${escapeHtml(message)}`;
    container.prepend(toast);

    setTimeout(() => {
        toast.style.opacity   = '0';
        toast.style.transform = 'translateX(20px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

/* ─── Utilitaire ─────────────────────────────────────────────────── */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(text)));
    return div.innerHTML;
}
