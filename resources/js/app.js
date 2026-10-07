import '@fontsource/plus-jakarta-sans/400.css';
import '@fontsource/plus-jakarta-sans/500.css';
import '@fontsource/plus-jakarta-sans/600.css';
import '@fontsource/plus-jakarta-sans/700.css';
import '@fontsource/ibm-plex-sans-arabic/400.css';
import '@fontsource/ibm-plex-sans-arabic/500.css';
import '@fontsource/ibm-plex-sans-arabic/600.css';
import '@fontsource/ibm-plex-sans-arabic/700.css';

import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, defineComponent } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import i18n, { applyDocumentLocale, resolveInitialLocale } from './i18n';
import { applyTheme, resolveInitialTheme } from './Composables/useTheme';
import AppErrorBoundary from './Components/Ui/AppErrorBoundary.vue';

const appName = import.meta.env.VITE_APP_NAME || 'WorkZone';

applyDocumentLocale(resolveInitialLocale());
applyTheme(resolveInitialTheme());

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const Root = defineComponent({
            name: 'AppRoot',
            setup() {
                return () =>
                    h(AppErrorBoundary, null, {
                        default: () => h(App, props),
                    });
            },
        });

        return createApp(Root)
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#0f6b5c',
    },
});
