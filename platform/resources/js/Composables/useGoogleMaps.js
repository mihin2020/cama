let loaderPromise = null;

export function loadGoogleMaps(apiKey) {
    if (!apiKey) {
        return Promise.reject(new Error('Clé Google Maps manquante'));
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

            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&callback=${callbackName}&loading=async`;
            script.async = true;
            script.defer = true;
            script.onerror = () => {
                loaderPromise = null;
                reject(new Error('Impossible de charger Google Maps'));
            };
            document.head.appendChild(script);
        });
    }

    return loaderPromise;
}
