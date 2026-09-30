import '../css/app.css';
import './bootstrap';

import CamaGlobalLoader from '@/Components/CamaGlobalLoader.vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const rawAppName = import.meta.env.VITE_APP_NAME;
// Ignore une valeur non résolue au build (ex. "${APP_NAME}" laissé littéral
// par Vite/Railway) : on retombe alors sur « CAMA ».
const appName = rawAppName && !rawAppName.includes('$') ? rawAppName : 'CAMA';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({
            render: () => h('div', [
                h(App, props),
                h(CamaGlobalLoader),
            ]),
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#9e001f',
        delay: 80,
        includeCSS: true,
        showSpinner: false,
    },
});
