/**
 * public/js/cart.js
 * Gestion du panier client RESTOSCAN (Vanilla JS, ES6+)
 * Stockage en mémoire JS + appel AJAX POST /order/create
 */

'use strict';

/* ─── État du panier ─────────────────────────────────────────────── */
const cart = {
    items: [],  // [{ id, nom, prix, quantite }]

    add(id, nom, prix) {
        const existing = this.items.find(i => i.id === id);
        if (existing) {
            existing.quantite++;
        } else {
            this.items.push({ id, nom, prix: parseFloat(prix), quantite: 1 });
        }
        this.notify();
    },

    remove(id) {
        this.items = this.items.filter(i => i.id !== id);
        this.notify();
    },

    setQty(id, qty) {
        const item = this.items.find(i => i.id === id);
        if (!item) return;
        if (qty <= 0) {
            this.remove(id);
        } else {
            item.quantite = qty;
            this.notify();
        }
    },

    get total() {
        return this.items.reduce((s, i) => s + i.prix * i.quantite, 0);
    },

    get count() {
        return this.items.reduce((s, i) => s + i.quantite, 0);
    },

    clear() {
        this.items = [];
        this.notify();
    },

    notify() {
        renderCartFab();
        renderCartDrawer();
    }
};

/* ─── Formatage prix ─────────────────────────────────────────────── */
function formatPrice(amount) {
    // BUG-13 : utilise la devise configuree dans Parametres (injectee par le layout)
    const devise = typeof DEVISE !== 'undefined' ? DEVISE : 'FCFA';
    return new Intl.NumberFormat('fr-FR').format(amount) + ' ' + devise;
}

/* ─── FAB panier flottant ────────────────────────────────────────── */
function renderCartFab() {
    const fab       = document.getElementById('cartFab');
    const countEl   = document.getElementById('cartCount');
    const totalEl   = document.getElementById('cartTotal');
    if (!fab) return;

    if (cart.count === 0) {
        fab.style.display = 'none';
        return;
    }
    fab.style.display = 'flex';
    countEl.textContent = cart.count;
    totalEl.textContent = formatPrice(cart.total);
}

/* ─── Contenu du drawer ──────────────────────────────────────────── */
function renderCartDrawer() {
    const bodyEl  = document.getElementById('cartItems');
    const totalEl = document.getElementById('cartTotalDrawer');
    if (!bodyEl) return;

    if (cart.items.length === 0) {
        bodyEl.innerHTML = `
            <div class="cart-empty">
                <i class="fa-solid fa-basket-shopping"></i>
                <p>Votre panier est vide</p>
            </div>`;
        if (totalEl) totalEl.textContent = formatPrice(0);
        return;
    }

    bodyEl.innerHTML = cart.items.map(item => `
        <div class="cart-item">
            <div class="cart-item__controls">
                <button class="cart-item__qty-btn" onclick="cartDecrement(${item.id})" aria-label="Retirer un">
                    <i class="fa-solid fa-minus"></i>
                </button>
                <span class="cart-item__qty">${item.quantite}</span>
                <button class="cart-item__qty-btn" onclick="cartIncrement(${item.id})" aria-label="Ajouter un">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>
            <span class="cart-item__name">${escapeHtml(item.nom)}</span>
            <span class="cart-item__price">${formatPrice(item.prix * item.quantite)}</span>
            <button class="cart-item__qty-btn" onclick="cart.remove(${item.id})" aria-label="Supprimer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    `).join('');

    if (totalEl) totalEl.textContent = formatPrice(cart.total);
}

/* ─── Actions publiques du panier ────────────────────────────────── */
function cartAdd(id, nom, prix) {
    cart.add(id, nom, prix);
    // Animation rapide sur le bouton
    const btn = document.querySelector(`.menu-card[data-id="${id}"] .btn-add`);
    if (btn) {
        btn.style.transform = 'scale(1.3)';
        setTimeout(() => { btn.style.transform = ''; }, 200);
    }
}

function cartIncrement(id) {
    const item = cart.items.find(i => i.id === id);
    if (item) cart.setQty(id, item.quantite + 1);
}

function cartDecrement(id) {
    const item = cart.items.find(i => i.id === id);
    if (item) cart.setQty(id, item.quantite - 1);
}

function cartOpen() {
    document.getElementById('cartOverlay')?.classList.add('open');
    document.getElementById('cartDrawer')?.classList.add('open');
}

function cartClose() {
    document.getElementById('cartOverlay')?.classList.remove('open');
    document.getElementById('cartDrawer')?.classList.remove('open');
}

/* ─── Soumettre la commande (AJAX) ───────────────────────────────── */
async function cartSubmit() {
    if (cart.count === 0) return;

    const btn   = document.getElementById('btnOrder');
    const notes = document.getElementById('orderNotes')?.value ?? '';

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Envoi…';
    }

    const payload = {
        qr_token: typeof TABLE_QR_TOKEN !== 'undefined' ? TABLE_QR_TOKEN : '',
        notes,
        items: cart.items.map(i => ({
            id:           i.id,
            quantite:     i.quantite,
            prix_unitaire: i.prix,
            notes:        '',
        })),
    };

    try {
        const res  = await fetch(BASE_URL + '/order/create', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.success) {
            cartClose();
            cart.clear();
            showOrderTracker(data.commande_id, data.total);
        } else {
            alert(data.error || 'Une erreur est survenue.');
        }
    } catch (err) {
        alert('Impossible d\'envoyer la commande. Vérifiez votre connexion.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Commander';
        }
    }
}

/* ─── Suivi de commande (polling) ────────────────────────────────── */
let trackingInterval = null;
const ORDER_STEPS = ['en_attente', 'en_preparation', 'pret', 'servi'];

function showOrderTracker(commandeId, total) {
    const tracker = document.getElementById('orderTracker');
    if (!tracker) return;

    document.getElementById('trackerTotal').textContent = formatPrice(total);
    tracker.style.display = 'flex';

    updateTrackerStep('en_attente');

    trackingInterval = setInterval(async () => {
        try {
            const res    = await fetch(`${BASE_URL}/order/status/${commandeId}`);
            const data   = await res.json();
            updateTrackerStep(data.statut);

            if (data.statut === 'servi' || data.statut === 'annule') {
                clearInterval(trackingInterval);
            }
        } catch {}
    }, 3000);
}

function updateTrackerStep(statut) {
    const statusIdx = ORDER_STEPS.indexOf(statut);
    const label     = document.getElementById('trackerStatusLabel');

    ORDER_STEPS.forEach((step, i) => {
        const el = document.getElementById('step-' + step);
        if (!el) return;
        el.classList.remove('active', 'done');
        if (i < statusIdx)  el.classList.add('done');
        if (i === statusIdx) el.classList.add('active');
    });

    const labels = {
        en_attente:     '⏳ Commande reçue, en attente…',
        en_preparation: '🔥 En cours de préparation…',
        pret:           '✅ Votre commande est prête !',
        servi:          '🎉 Bon appétit !',
    };
    if (label) label.textContent = labels[statut] || '';

    // Notification quand le plat est prêt
    if (statut === 'pret') {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('🍽️ Votre commande est prête !');
        }
    }
}

function newOrder() {
    clearInterval(trackingInterval);
    const tracker = document.getElementById('orderTracker');
    if (tracker) tracker.style.display = 'none';
    renderCartFab();
}

/* ─── Navigation catégories (scroll) ────────────────────────────── */
function scrollToCategory(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const offset = 54 + 48 + 16; // header + nav + espace
    const top    = el.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top, behavior: 'smooth' });
}

// Mettre à jour le bouton actif de la nav lors du scroll
function updateActiveNav() {
    const sections = document.querySelectorAll('.menu-section');
    const buttons  = document.querySelectorAll('.category-nav__btn');
    const offset   = 54 + 48 + 32;

    let current = '';
    sections.forEach(section => {
        const top = section.getBoundingClientRect().top;
        if (top <= offset + 20) current = section.id;
    });

    buttons.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.target === current);
    });
}

/* ─── Utilitaires ────────────────────────────────────────────────── */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

/* ─── Init ───────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    renderCartFab();
    renderCartDrawer();
    window.addEventListener('scroll', updateActiveNav, { passive: true });

    // Demander la permission notifications (pour "plat prêt")
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
});
