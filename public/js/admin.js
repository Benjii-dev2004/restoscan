/**
 * public/js/admin.js
 * Back-office RESTOSCAN — interactions UI (modals, sidebar, toggles)
 */

'use strict';

/* ─── Modals ─────────────────────────────────────────────────────── */
function modalOpen(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('modal--open');
    document.body.style.overflow = 'hidden';
    // Focus premier champ
    setTimeout(() => {
        const first = modal.querySelector('input, select, textarea');
        if (first) first.focus();
    }, 100);
}

function modalClose(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('modal--open');
    document.body.style.overflow = '';
}

// Fermer avec Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.modal--open').forEach(m => {
            modalClose(m.id);
        });
    }
});

/* ─── Sidebar mobile ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const toggle  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (toggle && sidebar && overlay) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        });
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });
    }
});
