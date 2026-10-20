import { reactive } from 'vue';

// Canal de communication éditeur (page parente) ⇄ page publique rendue dans l'iframe.
// Les deux documents sont sur la même origine : chaque message est vérifié contre
// window.location.origin, aucun autre site ne peut piloter l'aperçu.
export const PB_MESSAGE_SOURCE = 'cama-page-builder';

// Type MIME utilisé pour glisser un widget du panneau de l'éditeur vers l'iframe.
export const PB_WIDGET_MIME = 'application/x-cama-widget';

// État reçu de l'éditeur, lu par PublicCmsSections quand la page est rendue en aperçu.
export const pbPreview = reactive({
    // Sections en cours d'édition (null tant que l'éditeur n'a rien envoyé).
    sections: null,
    // Élément sélectionné dans l'éditeur : { kind: 'section' | 'column' | 'widget', uid }.
    selected: null,
    canPaste: false,
    // Incrémenté à chaque réception : permet de relancer les effets après un rendu.
    revision: 0,
});

export function postToBuilder(type, payload = {}) {
    if (window.parent === window) return;
    window.parent.postMessage({ source: PB_MESSAGE_SOURCE, type, ...payload }, window.location.origin);
}

export function postToPreview(frame, type, payload = {}) {
    frame?.contentWindow?.postMessage(
        // Aller-retour JSON : retire les proxys Vue, que postMessage ne sait pas cloner.
        JSON.parse(JSON.stringify({ source: PB_MESSAGE_SOURCE, type, ...payload })),
        window.location.origin,
    );
}

export function isBuilderMessage(event) {
    return event.origin === window.location.origin && event.data?.source === PB_MESSAGE_SOURCE;
}
