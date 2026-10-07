import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
import path from 'node:path';
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite';

export default defineConfig({
    plugins: [
        vue(),
        VueI18nPlugin({
            include: [path.resolve(__dirname, './resources/js/i18n/locales/**')],
            runtimeOnly: false,
            compositionOnly: true,
            fullInstall: false,
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            ziggy: fileURLToPath(new URL('./vendor/tightenco/ziggy', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.{test,spec}.{js,ts}'],
        setupFiles: ['resources/js/test/setup.js'],
    },
});
