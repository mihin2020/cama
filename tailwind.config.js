import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';
import { DEFAULT_TOKENS, hexToChannels } from './resources/js/Utils/siteTheme.js';

const themeColors = Object.fromEntries(
    Object.keys(DEFAULT_TOKENS).map((name) => [name, `rgb(var(--c-${name}) / <alpha-value>)`]),
);

// Valeurs par défaut des variables ; un thème personnalisé les surcharge dans app.blade.php.
const themeDefaults = plugin(({ addBase }) => {
    addBase({
        ':root': Object.fromEntries(
            Object.entries(DEFAULT_TOKENS).map(([name, hex]) => [`--c-${name}`, hexToChannels(hex)]),
        ),
    });
});

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                headline: ['Montserrat', ...defaultTheme.fontFamily.sans],
                'headline-lg': ['Montserrat', ...defaultTheme.fontFamily.sans],
                'headline-lg-mobile': ['Montserrat', ...defaultTheme.fontFamily.sans],
                'headline-md': ['Montserrat', ...defaultTheme.fontFamily.sans],
                'title-lg': ['Inter', ...defaultTheme.fontFamily.sans],
                'body-md': ['Inter', ...defaultTheme.fontFamily.sans],
                'body-lg': ['Inter', ...defaultTheme.fontFamily.sans],
                'label-md': ['Inter', ...defaultTheme.fontFamily.sans],
                caption: ['Inter', ...defaultTheme.fontFamily.sans],
                'display-lg': ['Montserrat', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                'headline-lg-mobile': ['24px', { lineHeight: '32px', fontWeight: '700' }],
                'title-lg': ['20px', { lineHeight: '28px', fontWeight: '600' }],
                'headline-lg': ['32px', { lineHeight: '40px', fontWeight: '700' }],
                'body-md': ['16px', { lineHeight: '24px', fontWeight: '400' }],
                'display-lg': ['48px', { lineHeight: '56px', letterSpacing: '-0.02em', fontWeight: '700' }],
                caption: ['12px', { lineHeight: '16px', fontWeight: '400' }],
                'label-md': ['14px', { lineHeight: '20px', letterSpacing: '0.05em', fontWeight: '600' }],
                'body-lg': ['18px', { lineHeight: '28px', fontWeight: '400' }],
                'headline-md': ['24px', { lineHeight: '32px', fontWeight: '600' }],
            },
            spacing: {
                'container-max-width': '1200px',
                'margin-desktop': '32px',
                'margin-mobile': '16px',
                gutter: '24px',
                'stack-sm': '8px',
                'stack-md': '16px',
                'stack-lg': '32px',
            },
            // Couleurs pilotées par variables CSS (cf. resources/js/Utils/siteTheme.js) :
            // modifiables depuis CMS > Identité visuelle sans recompiler le CSS.
            colors: themeColors,
            borderRadius: {
                DEFAULT: '0.125rem',
                lg: '0.25rem',
                xl: '0.5rem',
                full: '0.75rem',
            },
        },
    },
    plugins: [forms, themeDefaults],
};
