/**
 * Espace assuré CAMAsession, topbar, badge notifications, déconnexion
 */
(function () {
    'use strict';

    function initials(prenom, nom) {
        return ((prenom[0] || '') + (nom[0] || '')).toUpperCase();
    }

    function updateUnreadBadge() {
        const badge = document.getElementById('sidebar-unread-badge');
        if (!badge || !window.CamaAssureData) return;
        const unread = CamaAssureData.getUnreadAssureCount();
        if (unread > 0) {
            badge.textContent = unread > 9 ? '9+' : String(unread);
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    function initTopbar() {
        const profile = CamaAssureData.getAssureProfile();
        const nameEl = document.getElementById('topbar-name');
        if (!nameEl) return;
        nameEl.textContent = `${profile.prenom} ${profile.nom}`;
        document.getElementById('topbar-matricule').textContent = `Matricule ${profile.matricule}`;
        document.getElementById('topbar-avatar').textContent = initials(profile.prenom, profile.nom);
    }

    function initSidebar() {
        const toggle = document.getElementById('sidebar-toggle');
        const overlay = document.getElementById('sidebar-overlay');
        if (toggle) toggle.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
        if (overlay) overlay.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
    }

    function initLogout() {
        document.querySelectorAll('.sidebar-logout').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                CamaAssureData.logoutAssure();
                window.location.href = '../espace-assure.html';
            });
        });
    }

    function initAnchorTop() {
        const run = () => window.CamaAnchorTop?.init();
        if (window.CamaAnchorTop) {
            run();
            return;
        }
        const p = window.location.pathname.replace(/\\/g, '/');
        let src = 'anchor-top.js';
        if (p.includes('/admin/cms/') || p.includes('/assure/')) src = '../../anchor-top.js';
        else if (p.includes('/admin/')) src = '../anchor-top.js';
        const existing = document.querySelector('script[data-cama-anchor-top]');
        if (existing) {
            existing.addEventListener('load', run, { once: true });
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.dataset.camaAnchorTop = '1';
        script.onload = run;
        document.head.appendChild(script);
    }

    function init() {
        if (!window.CamaAssureData) return;
        if (!CamaAssureData.requireAssureSession()) return;
        initSidebar();
        initTopbar();
        updateUnreadBadge();
        initLogout();
        initAnchorTop();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.CamaAssureShell = { updateUnreadBadge, initials };
})();
