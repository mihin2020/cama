import { router } from '@inertiajs/vue3';

// Adresses servies hors Inertia (fichiers, téléchargements, suivi newsletter) : navigation classique.
const NON_PAGE_PATH = /^\/(storage|build|media|images|newsletter|sanctum|up)(\/|$)|\/telecharger(\/|$)|\/documents\//i;
const FILE_EXTENSION = /\.(pdf|docx?|xlsx?|csv|zip|jpe?g|jfif|png|gif|webp|svg|mp4|mp3)$/i;

/**
 * Chemin à ouvrir sans rechargement si `href` désigne une page de l'application,
 * sinon null (lien externe, ancre de la page, mailto:/tel:, fichier à télécharger).
 */
export function internalPath(href) {
    if (typeof href !== 'string' || typeof window === 'undefined') return null;
    const value = href.trim();
    if (!value || value.startsWith('#') || (/^[a-z][a-z0-9+.-]*:/i.test(value) && !/^https?:/i.test(value))) return null;

    let url;
    try {
        url = new URL(value, window.location.href);
    } catch {
        return null;
    }

    if (url.origin !== window.location.origin) return null;
    if (NON_PAGE_PATH.test(url.pathname) || FILE_EXTENSION.test(url.pathname)) return null;
    // Ancre sur la page courante : défilement natif.
    if (url.hash && url.pathname === window.location.pathname && url.search === window.location.search) return null;

    return url.pathname + url.search + url.hash;
}

/**
 * Gestionnaire de clic pour un conteneur de HTML libre (articles, texte riche, widgets) :
 * les liens internes y naviguent sans recharger la page. Ctrl/Cmd+clic, clic milieu,
 * target="_blank" et téléchargements gardent le comportement du navigateur.
 */
export function handleInternalLinkClick(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!anchor || anchor.hasAttribute('download') || (anchor.target && anchor.target !== '_self')) return;

    const path = internalPath(anchor.getAttribute('href'));
    if (!path) return;

    event.preventDefault();
    router.visit(path);
}
