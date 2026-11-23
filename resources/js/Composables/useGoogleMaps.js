let loaderPromise = null;

// Clé refusée par Google (invalide, facturation coupée, domaine non autorisé) : le script se charge quand même,
// et Google ne le signale que par window.gm_authFailure, éventuellement après l'affichage de la carte.
let authFailed = false;
const authFailureListeners = new Set();
window.gm_authFailure = () => {
    authFailed = true;
    authFailureListeners.forEach((listener) => listener());
};

/** Appelle `listener` si Google refuse la clé (immédiatement si c'est déjà le cas) ; renvoie de quoi se désinscrire. */
export function onGoogleMapsAuthFailure(listener) {
    if (authFailed) listener();
    authFailureListeners.add(listener);

    return () => authFailureListeners.delete(listener);
}

const pageNonce = () => document.querySelector('meta[name="csp-nonce"]')?.content ?? '';

/**
 * Google Maps reprend le nonce pour ses scripts, pas pour ses balises <style> (boutons, bulles…) : la CSP les
 * bloquerait. Seules les balises <style> créées par un script reçoivent le nonce ; un <style> glissé dans le
 * contenu HTML d'une page (injection) n'est pas créé ainsi et reste bloqué, et la CSP des scripts ne change pas.
 */
function giveNonceToScriptCreatedStyles(nonce) {
    if (!nonce || Document.prototype.createElement.camaNonce) return;

    const createElement = Document.prototype.createElement;
    const patched = function (tagName, options) {
        const element = createElement.call(this, tagName, options);
        if (String(tagName).toLowerCase() === 'style') element.nonce = nonce;
        return element;
    };
    patched.camaNonce = true;
    Document.prototype.createElement = patched;
}

export function loadGoogleMaps(apiKey) {
    if (!apiKey) {
        return Promise.reject(new Error('Clé Google Maps manquante'));
    }

    if (authFailed) {
        return Promise.reject(new Error('Clé Google Maps refusée'));
    }

    if (window.google?.maps) {
        return Promise.resolve(window.google.maps);
    }

    if (!loaderPromise) {
        loaderPromise = new Promise((resolve, reject) => {
            const callbackName = 'camaGoogleMapsInit';
            window[callbackName] = () => {
                delete window[callbackName];
                resolve(window.google.maps);
            };

            const nonce = pageNonce();
            giveNonceToScriptCreatedStyles(nonce);

            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&callback=${callbackName}&loading=async`;
            script.async = true;
            script.defer = true;
            // La CSP n'accepte que les scripts portant le nonce de la page ; Google Maps le reprend pour ses propres scripts.
            script.nonce = nonce;
            script.onerror = () => {
                loaderPromise = null;
                reject(new Error('Impossible de charger Google Maps'));
            };
            document.head.appendChild(script);
        });
    }

    return loaderPromise;
}
