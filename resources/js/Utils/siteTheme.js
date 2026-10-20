// Palette du site : source unique pour Tailwind (tailwind.config.js), les variables CSS
// par défaut et l'écran CMS > Identité visuelle. Chaque jeton devient la variable
// CSS --c-<jeton> (canaux « r g b »), ce qui garde les opacités Tailwind (bg-primary/10).

export const DEFAULT_TOKENS = {
    primary: '#9e001f',
    'primary-container': '#c8102e',
    'on-primary': '#ffffff',
    'on-primary-container': '#ffdad8',
    'primary-fixed': '#ffdad8',
    'primary-fixed-dim': '#ffb3b1',
    'inverse-primary': '#ffb3b1',
    'on-primary-fixed': '#410007',
    'on-primary-fixed-variant': '#92001c',
    'surface-tint': '#bf0229',

    secondary: '#006e27',
    'on-secondary': '#ffffff',
    'secondary-container': '#7ff98d',
    'on-secondary-container': '#007329',
    'secondary-fixed': '#81fc90',
    'secondary-fixed-dim': '#65df76',
    'on-secondary-fixed': '#002107',
    'on-secondary-fixed-variant': '#00531c',

    tertiary: '#745b00',
    'on-tertiary': '#ffffff',
    'tertiary-container': '#d0a600',
    'on-tertiary-container': '#4f3d00',
    'tertiary-fixed': '#ffe08a',
    'tertiary-fixed-dim': '#f1c100',
    'on-tertiary-fixed': '#241a00',
    'on-tertiary-fixed-variant': '#574400',

    background: '#fbf9f8',
    surface: '#fbf9f8',
    'surface-bright': '#fbf9f8',
    'surface-container-low': '#f6f3f2',
    'surface-container': '#f0eded',
    'surface-container-high': '#eae7e7',
    'surface-container-highest': '#e4e2e1',
    'surface-variant': '#e4e2e1',
    'surface-dim': '#dcd9d9',
    'inverse-on-surface': '#f3f0f0',
    'surface-container-lowest': '#ffffff',

    'on-background': '#1b1c1c',
    'on-surface': '#1b1c1c',
    'inverse-surface': '#303030',
    'on-surface-variant': '#5c403f',
    outline: '#906f6e',
    'outline-variant': '#e5bdbb',

    // Couleurs d'erreur : sémantiques, non modifiables (un rouge d'alerte doit rester rouge).
    error: '#ba1a1a',
    'on-error': '#ffffff',
    'error-container': '#ffdad6',
    'on-error-container': '#93000a',
};

// Les 8 couleurs proposées dans l'écran de réglage ; tout le reste en est déduit.
export const BASE_COLORS = [
    { key: 'primary', label: 'Couleur principale', hint: 'Boutons, liens, en-têtes, éléments actifs', default: '#9e001f' },
    { key: 'secondary', label: 'Couleur secondaire', hint: 'Validations, succès, badges', default: '#006e27' },
    { key: 'tertiary', label: "Couleur d'accent", hint: 'Avertissements, mises en avant', default: '#745b00' },
    { key: 'background', label: 'Fond des pages', hint: 'Arrière-plan général', default: '#fbf9f8' },
    { key: 'card', label: 'Fond des cartes', hint: 'Cartes, panneaux, champs de saisie', default: '#ffffff' },
    { key: 'text', label: 'Texte principal', hint: 'Titres et paragraphes', default: '#1b1c1c' },
    { key: 'textMuted', label: 'Texte secondaire', hint: 'Descriptions, légendes, libellés', default: '#5c403f' },
    { key: 'border', label: 'Bordures', hint: 'Contours des cartes et des champs', default: '#e5bdbb' },
];

export const DEFAULT_BASE = Object.fromEntries(BASE_COLORS.map((color) => [color.key, color.default]));

export const THEME_PRESETS = [
    { name: 'CAMA (origine)', base: DEFAULT_BASE },
    { name: 'Bleu institutionnel', base: { primary: '#0b4f9c', secondary: '#00796b', tertiary: '#9a5b00', background: '#f6f8fb', card: '#ffffff', text: '#14213d', textMuted: '#475569', border: '#cbd5e1' } },
    { name: 'Vert armée', base: { primary: '#3f5f1f', secondary: '#1f6f8b', tertiary: '#8a6a00', background: '#f7f8f3', card: '#ffffff', text: '#1c2116', textMuted: '#4f5745', border: '#cfd6c3' } },
    { name: 'Bordeaux sobre', base: { primary: '#7a1230', secondary: '#2e6b4f', tertiary: '#9c6b00', background: '#faf8f7', card: '#ffffff', text: '#211c1d', textMuted: '#5e5254', border: '#ddd2d4' } },
    { name: 'Gris anthracite', base: { primary: '#263445', secondary: '#1f7a4d', tertiary: '#a86b00', background: '#f5f6f7', card: '#ffffff', text: '#16191d', textMuted: '#525a63', border: '#d3d8de' } },
];

// ---- Conversions ---------------------------------------------------------------

export function normalizeHex(value) {
    const hex = String(value ?? '').trim().toLowerCase();
    if (/^#[0-9a-f]{6}$/.test(hex)) return hex;
    if (/^#[0-9a-f]{3}$/.test(hex)) return `#${hex[1]}${hex[1]}${hex[2]}${hex[2]}${hex[3]}${hex[3]}`;
    return null;
}

function hexToRgb(hex) {
    const value = parseInt(hex.slice(1), 16);
    return [(value >> 16) & 255, (value >> 8) & 255, value & 255];
}

function rgbToHex([r, g, b]) {
    return `#${[r, g, b].map((c) => Math.round(Math.min(255, Math.max(0, c))).toString(16).padStart(2, '0')).join('')}`;
}

export function hexToChannels(hex) {
    return hexToRgb(hex).join(' ');
}

function rgbToHsl([r, g, b]) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;
    if (max === min) return [0, 0, l * 100];
    const d = max - min;
    const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    const h = max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
    return [h * 60, s * 100, l * 100];
}

function hslToRgb([h, s, l]) {
    s /= 100; l /= 100;
    const k = (n) => (n + h / 30) % 12;
    const a = s * Math.min(l, 1 - l);
    const f = (n) => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
    return [f(0) * 255, f(8) * 255, f(4) * 255];
}

// Même teinte et saturation, luminosité imposée (0-100) : décline une couleur en nuances.
function tone(hex, lightness) {
    const [h, s] = rgbToHsl(hexToRgb(hex));
    return rgbToHex(hslToRgb([h, s, Math.min(98, Math.max(2, lightness))]));
}

function lightnessOf(hex) {
    return rgbToHsl(hexToRgb(hex))[2];
}

function mix(a, b, weight) {
    const ca = hexToRgb(a);
    const cb = hexToRgb(b);
    return rgbToHex(ca.map((c, i) => c + (cb[i] - c) * weight));
}

function luminance(hex) {
    return hexToRgb(hex)
        .map((c) => {
            const v = c / 255;
            return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
        })
        .reduce((sum, v, i) => sum + v * [0.2126, 0.7152, 0.0722][i], 0);
}

export function contrastRatio(a, b) {
    const [l1, l2] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (l1 + 0.05) / (l2 + 0.05);
}

// Texte lisible posé sur une couleur : blanc, ou très foncé si le fond est clair.
function onColor(hex) {
    return contrastRatio(hex, '#ffffff') >= contrastRatio(hex, '#1b1c1c') ? '#ffffff' : '#1b1c1c';
}

function pick(keys) {
    return Object.fromEntries(keys.map((key) => [key, DEFAULT_TOKENS[key]]));
}

// ---- Déclinaison des 8 couleurs en palette complète ------------------------------

function primaryFamily(p) {
    if (p === DEFAULT_BASE.primary) {
        return pick(['primary', 'primary-container', 'on-primary', 'on-primary-container', 'primary-fixed', 'primary-fixed-dim', 'inverse-primary', 'on-primary-fixed', 'on-primary-fixed-variant', 'surface-tint']);
    }
    const l = lightnessOf(p);
    const container = tone(p, Math.min(l + 11, 62));
    return {
        primary: p,
        'primary-container': container,
        'on-primary': onColor(p),
        'on-primary-container': onColor(container) === '#ffffff' ? tone(p, 92) : tone(p, 13),
        'primary-fixed': tone(p, 92),
        'primary-fixed-dim': tone(p, 85),
        'inverse-primary': tone(p, 85),
        'on-primary-fixed': tone(p, 13),
        'on-primary-fixed-variant': tone(p, Math.max(l - 3, 10)),
        'surface-tint': tone(p, Math.min(l + 7, 58)),
    };
}

function accentFamily(name, c, tones) {
    if (c === DEFAULT_BASE[name]) {
        return pick([name, `on-${name}`, `${name}-container`, `on-${name}-container`, `${name}-fixed`, `${name}-fixed-dim`, `on-${name}-fixed`, `on-${name}-fixed-variant`]);
    }
    const container = tone(c, tones.container);
    return {
        [name]: c,
        [`on-${name}`]: onColor(c),
        [`${name}-container`]: container,
        [`on-${name}-container`]: onColor(container) === '#ffffff' ? tone(c, 92) : tone(c, 16),
        [`${name}-fixed`]: tone(c, tones.fixed),
        [`${name}-fixed-dim`]: tone(c, tones.fixedDim),
        [`on-${name}-fixed`]: tone(c, 7),
        [`on-${name}-fixed-variant`]: tone(c, 17),
    };
}

function neutralFamily(base) {
    const { background, card, text, textMuted, border } = base;
    const neutrals = background === DEFAULT_BASE.background && text === DEFAULT_BASE.text
        ? pick(['background', 'surface', 'surface-bright', 'surface-container-low', 'surface-container', 'surface-container-high', 'surface-container-highest', 'surface-variant', 'surface-dim', 'inverse-on-surface', 'inverse-surface'])
        : {
            background,
            surface: background,
            'surface-bright': background,
            // Fonds gris successifs : le fond de page légèrement teinté par la couleur du texte.
            'surface-container-low': mix(background, text, 0.023),
            'surface-container': mix(background, text, 0.045),
            'surface-container-high': mix(background, text, 0.068),
            'surface-container-highest': mix(background, text, 0.09),
            'surface-variant': mix(background, text, 0.09),
            'surface-dim': mix(background, text, 0.125),
            'inverse-on-surface': mix(background, text, 0.03),
            'inverse-surface': mix(text, '#ffffff', 0.1),
        };

    return {
        ...neutrals,
        'surface-container-lowest': card,
        'on-background': text,
        'on-surface': text,
        'on-surface-variant': textMuted,
        outline: textMuted === DEFAULT_BASE.textMuted && background === DEFAULT_BASE.background
            ? DEFAULT_TOKENS.outline
            : mix(textMuted, background, 0.33),
        'outline-variant': border,
    };
}

export function deriveTheme(input = {}) {
    const base = Object.fromEntries(BASE_COLORS.map(({ key }) => [key, normalizeHex(input[key]) ?? DEFAULT_BASE[key]]));

    return {
        ...DEFAULT_TOKENS,
        ...primaryFamily(base.primary),
        ...accentFamily('secondary', base.secondary, { container: 74, fixed: 75, fixedDim: 63 }),
        ...accentFamily('tertiary', base.tertiary, { container: 41, fixed: 77, fixedDim: 47 }),
        ...neutralFamily(base),
    };
}

export function themeCssVariables(tokens) {
    return Object.fromEntries(Object.entries(tokens).map(([name, hex]) => [`--c-${name}`, hexToChannels(hex)]));
}

// Couleur réelle d'un jeton, lue sur la page (pour les API qui n'acceptent pas var(), ex. Google Maps).
export function themeColor(token, fallback = DEFAULT_TOKENS[token] ?? '#000000') {
    if (typeof window === 'undefined') return fallback;
    const channels = getComputedStyle(document.documentElement).getPropertyValue(`--c-${token}`).trim();
    return channels ? `rgb(${channels.split(/\s+/).join(', ')})` : fallback;
}

// Valeur affichable dans un <input type="color"> pour une couleur « rgb(var(--c-x)) » ou hexadécimale.
export function colorToHex(value, fallback = '#000000') {
    const hex = normalizeHex(value);
    if (hex) return hex;
    const match = String(value ?? '').match(/var\(--c-([a-z-]+)\)/);
    if (!match) return fallback;
    const rgb = themeColor(match[1], '').match(/\d+/g);
    return rgb ? rgbToHex(rgb.map(Number)) : (DEFAULT_TOKENS[match[1]] ?? fallback);
}

// Notation CSS d'un jeton : à utiliser partout à la place d'une couleur codée en dur.
export function themeVar(token) {
    return `rgb(var(--c-${token}))`;
}
