import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { LOCALE_KEY, applyDocumentLocale } from '@/i18n';

export function useLocale() {
    const { locale, t } = useI18n();

    const isRtl = computed(() => locale.value === 'ar');

    function setLocale(next) {
        if (next !== 'ar' && next !== 'en') {
            return;
        }

        locale.value = next;
        window.localStorage.setItem(LOCALE_KEY, next);
        applyDocumentLocale(next);
    }

    function toggleLocale() {
        setLocale(locale.value === 'ar' ? 'en' : 'ar');
    }

    return {
        locale,
        isRtl,
        t,
        setLocale,
        toggleLocale,
    };
}
