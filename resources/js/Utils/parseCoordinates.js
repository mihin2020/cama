/**
 * Extrait latitude / longitude d'un lien Google Maps ou d'un texte « 12.37145, -1.51972 ».
 * Retourne { lat, lon } ou null. Les liens courts (maps.app.goo.gl) ne contiennent pas
 * les coordonnées : il faut ouvrir le lien puis copier l'adresse complète.
 */
const PATTERNS = [
    /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/, // repère exact du lieu
    /[?&](?:q|query|ll|destination|center)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
    /@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/, // centre de la vue
    /^\s*(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)\s*$/, // coordonnées collées
];

export function parseCoordinates(text) {
    let value = String(text ?? '').trim();
    try {
        value = decodeURIComponent(value);
    } catch {
        /* texte non encodé : on le garde tel quel */
    }

    for (const pattern of PATTERNS) {
        const match = value.match(pattern);
        if (!match) continue;
        const lat = Number(match[1]);
        const lon = Number(match[2]);
        if (Math.abs(lat) <= 90 && Math.abs(lon) <= 180) {
            return { lat: Math.round(lat * 1e7) / 1e7, lon: Math.round(lon * 1e7) / 1e7 };
        }
    }
    return null;
}

/** Le point tombe-t-il au Burkina Faso (cadre large) ? Sert à signaler une inversion lat/lon. */
export function isInBurkinaFaso({ lat, lon }) {
    return lat >= 9 && lat <= 15.2 && lon >= -5.6 && lon <= 2.5;
}
