/**
 * Garde d'authentification back-office — à charger dans le <head> avant le body.
 */
(function () {
    'use strict';
    const SESSION_KEY = 'cama_admin_session';
    const path = window.location.pathname.replace(/\\/g, '/');
    const onLogin = /login\.html$/i.test(path) || path.endsWith('/admin/login');
    const inAdmin = path.includes('/admin/');

    function hasSession() {
        try {
            const raw = localStorage.getItem(SESSION_KEY);
            if (!raw) return false;
            const s = JSON.parse(raw);
            return !!(s && s.role && s.email);
        } catch {
            return false;
        }
    }

    function loginPath() {
        return path.includes('/admin/cms/') ? '../login.html' : 'login.html';
    }

    function dashboardPath() {
        return path.includes('/admin/cms/') ? '../dashboard.html' : 'dashboard.html';
    }

    if (!document.getElementById('cama-auth-guard-style')) {
        const style = document.createElement('style');
        style.id = 'cama-auth-guard-style';
        style.textContent = 'html.cama-auth-pending body{visibility:hidden}';
        document.head.appendChild(style);
    }

    document.documentElement.classList.add('cama-auth-pending');

    if (onLogin) {
        if (hasSession()) {
            window.location.replace(dashboardPath());
            return;
        }
        document.documentElement.classList.remove('cama-auth-pending');
        return;
    }

    if (inAdmin && !hasSession()) {
        window.location.replace(loginPath());
        return;
    }

    if (inAdmin) {
        document.documentElement.classList.remove('cama-auth-pending');
    }
})();
