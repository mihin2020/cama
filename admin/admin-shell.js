/**
 * Back-office CAMAcloche notifications header + sync badges
 */
(function () {
    'use strict';

    const NOTIF_KEY = 'cama_admin_notifs';

    const DEFAULT_NOTIFS = [
        { type: 'soumission', icon: 'upload_file', color: 'primary', titre: 'Nouvelle soumission', contenu: 'Fatoumata TRAORÉdossier CAMA-2026-15302 soumis.', date: '12/06/2026 13:10', lu: false, lien: 'dossiers.html' },
        { type: 'retard', icon: 'schedule', color: 'error', titre: 'Dossier en retard', contenu: 'CAMA-2025-91007 dépasse le délai moyen de traitement.', date: '10/06/2026 09:00', lu: false, lien: 'dossiers.html' },
        { type: 'supervision', icon: 'verified_user', color: 'tertiary', titre: 'Validation niveau 1', contenu: 'CAMA-2026-20155 en attente de validation superviseur.', date: '18/06/2026 11:20', lu: false, lien: 'dossiers.html' },
        { type: 'compte', icon: 'person_add', color: 'tertiary', titre: 'Nouveau compte assuré', contenu: 'David KONÉ a créé un compte, en attente de validation.', date: '17/06/2026 09:00', lu: false, lien: 'assures.html' },
        { type: 'export', icon: 'file_download', color: 'secondary', titre: 'Export terminé', contenu: 'Export CSV du journal d\'audit (01/05 → 31/05) prêt.', date: '01/06/2026 08:31', lu: true, lien: 'exports.html' }
    ];

    function getNotifs() {
        try {
            const stored = localStorage.getItem(NOTIF_KEY);
            if (stored) return JSON.parse(stored);
        } catch (_) { /* ignore */ }
        return DEFAULT_NOTIFS.map(n => ({ ...n }));
    }

    function saveNotifs(notifs) {
        localStorage.setItem(NOTIF_KEY, JSON.stringify(notifs));
        updateBadges();
        renderDropdown();
    }

    function unreadCount() {
        return getNotifs().filter(n => !n.lu).length;
    }

    function updateBadges() {
        const count = unreadCount();
        document.querySelectorAll('#sidebar-unread-badge, #header-notif-badge').forEach(badge => {
            if (!badge) return;
            if (count > 0) {
                badge.textContent = count > 9 ? '9+' : String(count);
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        });
    }

    function resolveLink(lien) {
        if (!lien) return null;
        const path = window.location.pathname.replace(/\\/g, '/');
        if (path.includes('/admin/cms/')) {
            return lien.startsWith('cms/') ? lien : '../' + lien;
        }
        return lien;
    }

    function renderDropdown() {
        const list = document.getElementById('admin-notif-list');
        if (!list) return;
        const notifs = getNotifs().slice(0, 5);
        list.innerHTML = notifs.length ? notifs.map((n, i) => {
            const href = resolveLink(n.lien);
            return `<button type="button" class="admin-notif-item w-full text-left flex gap-2.5 px-3 py-2.5 hover:bg-surface-container-low transition-colors ${n.lu ? '' : 'bg-primary/5'}" data-idx="${i}" ${href ? `data-href="${href}"` : ''}>
                <span class="material-symbols-outlined text-[18px] text-${n.color} shrink-0 mt-0.5">${n.icon}</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-xs font-bold text-on-surface truncate">${n.titre}${n.lu ? '' : ' •'}</span>
                    <span class="block text-[11px] text-on-surface-variant line-clamp-2">${n.contenu}</span>
                    <span class="block text-[10px] text-on-surface-variant mt-0.5">${n.date}</span>
                </span>
            </button>`;
        }).join('') : '<p class="px-3 py-4 text-xs text-on-surface-variant text-center">Aucune notification.</p>';

        list.querySelectorAll('.admin-notif-item').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.dataset.idx, 10);
                markRead(idx);
                const href = btn.dataset.href;
                if (href) window.location.href = href;
                closeDropdown();
            });
        });
    }

    function markRead(index) {
        const notifs = getNotifs();
        if (notifs[index]) {
            notifs[index].lu = true;
            saveNotifs(notifs);
        }
    }

    function markAllRead() {
        saveNotifs(getNotifs().map(n => ({ ...n, lu: true })));
    }

    function addNotif(notif) {
        const notifs = getNotifs();
        notifs.unshift({ lu: false, ...notif });
        saveNotifs(notifs);
    }

    function closeDropdown() {
        document.getElementById('admin-notif-dropdown')?.classList.remove('is-open');
        document.getElementById('admin-notif-toggle')?.setAttribute('aria-expanded', 'false');
    }

    function mountBell() {
        const header = document.getElementById('app-header');
        if (!header || document.getElementById('admin-notif-wrap')) return;

        const containers = header.querySelectorAll(':scope > .flex.items-center.gap-3');
        const rightContainer = containers[containers.length - 1];
        if (!rightContainer) return;

        const cmsPath = window.location.pathname.replace(/\\/g, '/').includes('/admin/cms/');
        const notifPage = cmsPath ? '../notifications.html' : 'notifications.html';

        const wrap = document.createElement('div');
        wrap.id = 'admin-notif-wrap';
        wrap.className = 'relative shrink-0';
        wrap.innerHTML = `
            <button type="button" id="admin-notif-toggle" class="admin-notif-btn relative w-10 h-10 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors" aria-label="Notifications" aria-expanded="false" aria-haspopup="true">
                <span class="material-symbols-outlined text-[22px]">notifications</span>
                <span id="header-notif-badge" class="hidden absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-primary text-on-primary text-[10px] font-bold flex items-center justify-center">0</span>
            </button>
            <div id="admin-notif-dropdown" class="admin-notif-dropdown absolute right-0 top-full mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white rounded-xl border border-outline-variant shadow-xl overflow-hidden z-50">
                <div class="px-3 py-2.5 border-b border-outline-variant flex items-center justify-between bg-surface-container-low">
                    <span class="text-xs font-bold text-on-surface">Notifications</span>
                    <a href="${notifPage}" class="text-[11px] font-semibold text-primary hover:underline">Voir tout</a>
                </div>
                <div id="admin-notif-list" class="max-h-72 overflow-y-auto divide-y divide-outline-variant"></div>
                <div class="px-3 py-2 border-t border-outline-variant">
                    <button type="button" id="admin-notif-mark-all" class="w-full text-center text-[11px] font-semibold text-on-surface-variant hover:text-primary py-1">Tout marquer comme lu</button>
                </div>
            </div>`;

        const profileBlock = rightContainer.querySelector('#topbar-avatar')?.closest('.flex.items-center.gap-3')
            || rightContainer.lastElementChild;
        if (profileBlock) {
            rightContainer.insertBefore(wrap, profileBlock);
        } else {
            rightContainer.prepend(wrap);
        }

        document.getElementById('admin-notif-toggle')?.addEventListener('click', (e) => {
            e.stopPropagation();
            const dd = document.getElementById('admin-notif-dropdown');
            const open = dd.classList.toggle('is-open');
            document.getElementById('admin-notif-toggle').setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.getElementById('admin-notif-mark-all')?.addEventListener('click', markAllRead);
        document.addEventListener('click', closeDropdown);
        wrap.addEventListener('click', e => e.stopPropagation());

        renderDropdown();
        updateBadges();
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
        if (!localStorage.getItem('cama_admin_session')) {
            const path = window.location.pathname.replace(/\\/g, '/');
            if (!path.includes('login.html')) {
                window.location.href = path.includes('/admin/cms/') ? '../login.html' : 'login.html';
                return;
            }
        }
        if (!localStorage.getItem(NOTIF_KEY)) {
            localStorage.setItem(NOTIF_KEY, JSON.stringify(DEFAULT_NOTIFS));
        }
        mountBell();
        document.querySelectorAll('.sidebar-logout').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                if (window.CamaAssureData?.logoutAdmin) {
                    CamaAssureData.logoutAdmin();
                } else {
                    localStorage.removeItem('cama_admin_session');
                    localStorage.removeItem('cama_admin_role');
                }
                const path = window.location.pathname.replace(/\\/g, '/');
                window.location.href = path.includes('/admin/cms/') ? '../login.html' : 'login.html';
            });
        });
        initAnchorTop();
    }

    window.CamaAdminShell = {
        getNotifs,
        saveNotifs,
        markRead,
        markAllRead,
        addNotif,
        updateBadges,
        isValidation2Niveaux() {
            return localStorage.getItem('cama_validation_2niveaux') === 'true';
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
