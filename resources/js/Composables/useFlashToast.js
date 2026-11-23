import { ref } from 'vue';

/**
 * Toast unique de l'application (sombre, en bas à droite) : messages flash du serveur et messages des pages.
 * L'état est partagé : deux appels rapprochés remplacent le message au lieu d'empiler deux toasts.
 */
const KINDS = {
    success: 'check_circle',
    warning: 'warning',
    error: 'error',
    info: 'info',
};

export const currentToast = ref(null);

/** Composants d'affichage montés : seul le premier dessine le toast (une page peut en contenir deux). */
export const toastHosts = ref([]);
let timer = null;
let sequence = 0;

/** @param {string} text  @param {string} type  « success », « warning », « error », « info » ou un nom d'icône Material */
export function notify(text, type = 'success') {
    if (!text) return;

    const kind = KINDS[type] ? type : 'success';
    const icon = KINDS[type] ?? type;

    window.clearTimeout(timer);
    currentToast.value = { id: ++sequence, text: String(text), icon, kind };
    // Une alerte se lit plus lentement qu'une confirmation.
    timer = window.setTimeout(() => { currentToast.value = null; }, kind === 'error' || kind === 'warning' ? 6000 : 3500);
}

export function dismissToast() {
    window.clearTimeout(timer);
    currentToast.value = null;
}
