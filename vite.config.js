import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite';
import { fileURLToPath, URL } from 'node:url';
import path from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VueI18nPlugin({
            include: [path.resolve(__dirname, './resources/js/i18n/locales/**')],
            runtimeOnly: true,
            compositionOnly: true,
            fullInstall: false,
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            // Runtime-only builds — no browser template compiler / message compiler.
            vue: 'vue/dist/vue.runtime.esm-bundler.js',
            'vue-i18n': 'vue-i18n/dist/vue-i18n.runtime.mjs',
        },
    },
    define: {
        __VUE_OPTIONS_API__: true,
        __VUE_PROD_DEVTOOLS__: false,
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false,
        __INTLIFY_JIT_COMPILATION__: false,
        __INTLIFY_DROP_MESSAGE_COMPILER__: true,
    },
});
