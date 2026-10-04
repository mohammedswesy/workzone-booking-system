import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import ar from './locales/ar.json';

export const LOCALE_KEY = 'wz_locale';

export function resolveInitialLocale() {
    if (typeof window === 'undefined') {
        return 'ar';
    }

    const saved = window.localStorage.getItem(LOCALE_KEY);
    if (saved === 'ar' || saved === 'en') {
        return saved;
    }

    return navigator.language?.toLowerCase().startsWith('ar') ? 'ar' : 'en';
}

export function applyDocumentLocale(locale) {
    if (typeof document === 'undefined') {
        return;
    }

    const dir = locale === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.lang = locale;
    document.documentElement.dir = dir;
}

const locale = resolveInitialLocale();
applyDocumentLocale(locale);

const i18n = createI18n({
    legacy: false,
    locale,
    fallbackLocale: 'en',
    messages: { en, ar },
});

export default i18n;
