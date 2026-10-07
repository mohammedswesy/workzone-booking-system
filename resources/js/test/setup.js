import { config } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import en from '@/i18n/locales/en.json';
import ar from '@/i18n/locales/ar.json';

const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages: { en, ar },
});

config.global.plugins = [i18n];

config.global.mocks = {
    route: (name) => `/${String(name).replace(/\./g, '/')}`,
};

globalThis.route = (name) => `/${String(name).replace(/\./g, '/')}`;
