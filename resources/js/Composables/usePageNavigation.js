import { router } from '@inertiajs/vue3';
import { readonly, ref } from 'vue';

/**
 * Indique qu'un changement de page est en cours, pour afficher un squelette à la place
 * de l'ancien contenu. Ne concerne que les vraies navigations : pas les envois de formulaire
 * (déjà couverts par CamaGlobalLoader), ni les rechargements partiels, ni les filtres qui
 * conservent l'état de la page. Le délai évite un clignotement quand la réponse est rapide.
 */
const DELAY_MS = 180;

const navigating = ref(false);
let timer = null;
let installed = false;

function isPageNavigation(visit) {
    if (!visit || (visit.method ?? 'get').toLowerCase() !== 'get') return false;
    if (visit.prefetch || visit.async) return false;
    if (Array.isArray(visit.only) && visit.only.length) return false;
    if (Array.isArray(visit.except) && visit.except.length) return false;
    return visit.preserveState !== true;
}

function stop() {
    clearTimeout(timer);
    timer = null;
    navigating.value = false;
}

function install() {
    if (installed || typeof window === 'undefined') return;
    installed = true;

    router.on('start', (event) => {
        if (!isPageNavigation(event.detail.visit)) return;
        clearTimeout(timer);
        timer = setTimeout(() => {
            navigating.value = true;
        }, DELAY_MS);
    });
    router.on('finish', stop);
}

export function usePageNavigation() {
    install();
    return { navigating: readonly(navigating) };
}
